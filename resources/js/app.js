import './bootstrap';
import Alpine from 'alpinejs';
import { videoUploader } from './video-uploader';
import { copyButton } from './copy';
import './money';
import { barChart } from './charts';
import { uploadQueue } from './upload-queue';

/**
 * Modal do player: qualquer botão "Assistir" chama $store.player.open(video).
 */
Alpine.store('player', {
    video: null,

    open(video) {
        this.video = video;
        document.documentElement.classList.add('overflow-hidden');
    },

    close() {
        this.video = null;
        document.documentElement.classList.remove('overflow-hidden');
    },
});

// Janela do olhinho (senha para mostrar os valores em dinheiro).
Alpine.store('finance', { open: false });

Alpine.data('coverPreview', (initialUrl = null) => ({
    url: initialUrl,

    pick(event) {
        const file = event.target.files[0];
        if (!file) return;
        if (this.url?.startsWith('blob:')) URL.revokeObjectURL(this.url);
        this.url = URL.createObjectURL(file);
    },
}));

Alpine.data('videoUploader', videoUploader);
Alpine.data('copyButton', copyButton);
Alpine.data('barChart', barChart);
Alpine.data('uploadQueue', uploadQueue);

window.Alpine = Alpine;
Alpine.start();
