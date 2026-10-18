import axios from 'axios';

const PARALLEL = 3; // partes enviadas ao mesmo tempo
const MAX_RETRIES = 4;
const SIGN_BATCH = 100; // a Noxem assina no máximo 100 partes por pedido
const URL_MARGIN_MS = 10 * 60 * 1000; // pede URL nova se faltar menos que isso para vencer

const fill = (template, token) => template.replace('__TOKEN__', encodeURIComponent(token));

/**
 * PUT de uma fatia direto na URL pré-assinada do storage (sem a API Key, sem headers extras).
 * XMLHttpRequest para ter o progresso do envio.
 */
function putPart(url, blob, signal, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const onAbort = () => xhr.abort();

        xhr.open('PUT', url);
        xhr.upload.onprogress = (e) => onProgress(e.loaded);
        xhr.onload = () => {
            signal?.removeEventListener('abort', onAbort);
            if (xhr.status >= 200 && xhr.status < 300) resolve();
            else reject(Object.assign(new Error(`PUT ${xhr.status}`), { status: xhr.status }));
        };
        xhr.onerror = () => {
            signal?.removeEventListener('abort', onAbort);
            reject(Object.assign(new Error('Falha de rede'), { status: 0 }));
        };
        xhr.onabort = () => reject(Object.assign(new Error('Envio cancelado'), { aborted: true }));
        signal?.addEventListener('abort', onAbort);
        xhr.send(blob);
    });
}

/**
 * Envia o arquivo para a Noxem Cloud e devolve o token do envio concluído (vai no campo video_token).
 * 1. o servidor cria o vídeo/multipart e devolve o plano (partSize, partCount, URLs);
 * 2. cada parte n = file.slice((n - 1) * partSize, … + part.size) vai por PUT direto ao storage;
 * 3. o servidor fecha o multipart e a Noxem começa a preparar o vídeo.
 */
export async function uploadToNoxem(file, config, onProgress, signal) {
    const urls = config.noxem;
    const { data: plan } = await axios.post(
        urls.startUrl,
        { filename: file.name, filesize: file.size, content_type: file.type || null },
        { signal },
    );

    const token = plan.token;
    const signed = new Map(); // partNumber -> { url, size, expiresAt }
    const remember = (parts, expiresIn) => {
        const expiresAt = Date.now() + expiresIn * 1000;
        for (const part of parts) signed.set(part.partNumber, { ...part, expiresAt });
    };
    remember(plan.parts, plan.expiresIn);

    const loaded = new Map(); // partNumber -> bytes enviados
    const report = () => {
        let sum = 0;
        for (const bytes of loaded.values()) sum += bytes;
        onProgress(Math.min(99, (sum / file.size) * 100));
    };

    // Pede URLs novas (lote de até 100 a partir da parte n) quando faltam ou estão para vencer.
    let signing = null;
    async function urlFor(n, force = false) {
        const current = signed.get(n);
        if (!force && current && current.expiresAt - Date.now() > URL_MARGIN_MS) return current;

        signing ??= (async () => {
            const numbers = [];
            for (let i = n; i <= plan.partCount && numbers.length < SIGN_BATCH; i++) {
                const known = signed.get(i);
                if (i === n || !known || known.expiresAt - Date.now() <= URL_MARGIN_MS) numbers.push(i);
            }
            const { data } = await axios.post(fill(urls.signUrl, token), { part_numbers: numbers }, { signal });
            remember(data.parts, data.expiresIn);
        })().finally(() => (signing = null));
        await signing;

        return signed.get(n);
    }

    async function sendPart(n) {
        for (let attempt = 1; ; attempt++) {
            const part = await urlFor(n, attempt > 1);
            const start = (n - 1) * plan.partSize;
            try {
                await putPart(part.url, file.slice(start, start + part.size), signal, (bytes) => {
                    loaded.set(n, bytes);
                    report();
                });
                loaded.set(n, part.size);
                report();
                return;
            } catch (error) {
                loaded.set(n, 0);
                if (error.aborted || signal?.aborted || attempt >= MAX_RETRIES) throw error;
                // 403 = URL vencida/recusada: a próxima tentativa pede outra URL.
                await new Promise((r) => setTimeout(r, 1000 * attempt));
            }
        }
    }

    try {
        let next = 1;
        const worker = async () => {
            while (next <= plan.partCount) {
                const n = next++;
                await sendPart(n);
            }
        };
        await Promise.all(Array.from({ length: Math.min(PARALLEL, plan.partCount) }, worker));

        const { data } = await axios.post(fill(urls.completeUrl, token), {}, { signal });
        return data.token;
    } catch (error) {
        // Cancelado ou falhou de vez: libera o envio aberto na Noxem (sem esperar a resposta).
        axios.post(fill(urls.abortUrl, token)).catch(() => {});
        if (error.status === 0 || (error.status && !error.response)) {
            throw Object.assign(new Error('upload'), {
                response: { data: { message: 'Falha ao enviar o vídeo para a Noxem Cloud. Verifique a internet e tente novamente.' } },
            });
        }
        throw error;
    }
}
