/**
 * Vídeos na Noxem Cloud tocam por uma URL temporária da CDN (o servidor redireciona para ela).
 * Se a URL vencer no meio do filme (pausado por muito tempo), o próximo pedido de Range falha:
 * aqui o player pede o endereço de novo ao servidor (que redireciona para uma URL nova) e
 * continua de onde parou. Vale para vídeo local também (queda de rede). Uma tentativa a cada 30 s.
 */
export function recoverOnExpiry(video) {
    const src = video.getAttribute('src');
    let lastTry = 0;
    let playing = false;

    video.addEventListener('play', () => (playing = true));
    video.addEventListener('pause', () => (playing = false));

    video.addEventListener('error', (event) => {
        const code = video.error?.code;
        const retryable = code === MediaError.MEDIA_ERR_NETWORK || code === MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED;
        if (!src || !retryable || Date.now() - lastTry < 30_000) return; // deixa a tela de erro aparecer

        lastTry = Date.now();
        event.stopImmediatePropagation();

        const time = video.currentTime;
        const resume = playing;
        video.addEventListener(
            'loadedmetadata',
            () => {
                if (time) video.currentTime = time;
                if (resume) video.play().catch(() => {});
            },
            { once: true },
        );
        video.src = src;
        video.load();
    });
}
