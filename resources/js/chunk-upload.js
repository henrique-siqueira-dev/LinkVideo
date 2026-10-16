import axios from 'axios';

const MAX_RETRIES = 3;

export function uuid() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();

    // crypto.randomUUID só existe em HTTPS/localhost.
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
        (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16),
    );
}

export function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) {
        bytes /= 1024;
        i++;
    }
    return `${bytes.toLocaleString('pt-BR', { maximumFractionDigits: i ? 1 : 0 })} ${units[i]}`;
}

/** Lê duração (segundos), largura e altura do arquivo local antes do envio. */
export function readVideoInfo(file) {
    return new Promise((resolve) => {
        const video = document.createElement('video');
        const url = URL.createObjectURL(file);
        const done = (value) => {
            URL.revokeObjectURL(url);
            resolve(value);
        };
        video.preload = 'metadata';
        video.onloadedmetadata = () =>
            done({
                duration: Number.isFinite(video.duration) ? Math.round(video.duration) : null,
                width: video.videoWidth || null,
                height: video.videoHeight || null,
            });
        video.onerror = () => done({ duration: null, width: null, height: null });
        video.src = url;
    });
}

/** Mensagem amigável para um erro de envio. */
export function uploadErrorMessage(error) {
    if (error.response?.status === 419) return 'Sua sessão expirou. Recarregue a página e entre novamente.';
    return (
        error.response?.data?.errors?.chunk?.[0] ??
        error.response?.data?.message ??
        'Falha na conexão durante o envio. Tente novamente.'
    );
}

async function postWithRetry(url, form, signal, onProgress) {
    for (let attempt = 1; ; attempt++) {
        try {
            return await axios.post(url, form, { signal, onUploadProgress: (e) => onProgress(e.loaded) });
        } catch (error) {
            // Erros de validação (4xx) não adiantam repetir.
            const status = error.response?.status;
            if (signal?.aborted || attempt >= MAX_RETRIES || (status && status < 500)) throw error;
            await new Promise((r) => setTimeout(r, 1000 * attempt));
        }
    }
}

/**
 * Envia o arquivo em partes para /uploads/chunk e devolve o token do upload concluído.
 * onProgress recebe a porcentagem (0–99) conforme as partes sobem.
 */
export async function uploadInChunks(file, { url, chunkSize }, onProgress, signal) {
    const uploadId = uuid();
    const total = Math.ceil(file.size / chunkSize);

    for (let index = 0; index < total; index++) {
        const start = index * chunkSize;
        const form = new FormData();
        form.append('upload_id', uploadId);
        form.append('index', index);
        form.append('total', total);
        form.append('filename', file.name);
        form.append('filesize', file.size);
        form.append('chunk', file.slice(start, Math.min(start + chunkSize, file.size)), `${uploadId}.part`);

        const response = await postWithRetry(url, form, signal, (loaded) => {
            onProgress(Math.min(99, ((start + loaded) / file.size) * 100));
        });

        if (response.data.done) return response.data.token;
    }

    throw new Error('Upload terminou sem confirmação do servidor.');
}
