/**
 * Tela do link fixo (film/watch.blade.php). Leve de propósito: sem Alpine, sem o JS do painel.
 *
 * Abre com a capa e o botão grande de assistir; o vídeo já vai sendo baixado por trás
 * (preload="auto"). O clique é o que libera o som e a tela cheia — os navegadores só
 * permitem os dois depois de um toque/clique da pessoa —, então um clique só:
 * toca com som, abre a tela cheia e, no celular, deita a tela se o vídeo for horizontal.
 */
import { recoverOnExpiry } from './media-recover';

const stage = document.querySelector('[data-film]');
const video = stage?.querySelector('video');

// Sem <video> = filme ainda em preparo na Noxem Cloud (a página mostra só o aviso).
if (stage && video) {
    // Antes do listener de erro abaixo: renova a URL da CDN antes de mostrar a mensagem de erro.
    recoverOnExpiry(video);

    const loading = stage.querySelector('[data-film-loading]');
    const start = stage.querySelector('[data-film-start]');
    const error = stage.querySelector('[data-film-error]');

    const isVertical = () =>
        video.videoWidth ? video.videoHeight > video.videoWidth : stage.dataset.vertical === '1';

    const setLoading = (on) => (loading.hidden = !on);

    function lockOrientation() {
        screen.orientation?.lock?.(isVertical() ? 'portrait' : 'landscape').catch(() => {});
    }

    function enterFullscreen() {
        const request = video.requestFullscreen ?? video.webkitRequestFullscreen;
        try {
            if (request) {
                Promise.resolve(request.call(video)).then(lockOrientation, () => {});
            } else {
                video.webkitEnterFullscreen?.(); // iPhone
            }
        } catch {
            // Sem tela cheia neste aparelho: segue tocando na página.
        }
    }

    start.addEventListener('click', () => {
        start.hidden = true;
        video.controls = true;
        setLoading(video.readyState < HTMLMediaElement.HAVE_FUTURE_DATA);
        video.play().catch(() => {
            setLoading(false);
            start.hidden = false;
        });
        enterFullscreen();
    });

    video.addEventListener('loadedmetadata', () => (stage.dataset.vertical = isVertical() ? '1' : '0'));
    video.addEventListener('waiting', () => !video.paused && setLoading(true));
    video.addEventListener('playing', () => {
        setLoading(false);
        stage.dataset.playing = '';
    });
    for (const event of ['pause', 'ended']) {
        video.addEventListener(event, () => delete stage.dataset.playing);
    }
    video.addEventListener('error', () => {
        setLoading(false);
        start.hidden = true;
        error.hidden = false;
    });

    document.addEventListener('fullscreenchange', () => {
        try {
            if (!document.fullscreenElement) screen.orientation?.unlock?.();
        } catch {
            // Aparelho sem controle de orientação.
        }
    });
}
