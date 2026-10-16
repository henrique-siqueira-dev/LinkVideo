import { formatBytes, readVideoInfo, uploadErrorMessage, uploadInChunks } from './chunk-upload';

/**
 * Envia o vídeo em partes para /uploads/chunk e guarda o token devolvido
 * no input hidden "video_token" do formulário.
 */
export function videoUploader(config) {
    return {
        status: config.token ? 'done' : 'idle', // idle | uploading | done | error
        token: config.token ?? '',
        name: config.name ?? '',
        size: config.size ?? 0,
        duration: config.duration ?? '',
        width: '',
        height: '',
        progress: config.token ? 100 : 0,
        error: '',
        dragging: false,
        submitting: false,
        controller: null,

        get sizeLabel() {
            return this.size ? formatBytes(this.size) : '';
        },

        get uploadedLabel() {
            return formatBytes((this.size * this.progress) / 100);
        },

        onInput(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (file) this.start(file);
        },

        onDrop(event) {
            this.dragging = false;
            const file = event.dataTransfer.files[0];
            if (file) this.start(file);
        },

        validate(file) {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!config.extensions.includes(extension)) {
                return `Formato não suportado. Use: ${config.extensions.join(', ').toUpperCase()}.`;
            }
            if (file.size > config.maxBytes) {
                return `O vídeo excede o limite de ${config.maxLabel}.`;
            }
            return null;
        },

        async start(file) {
            this.cancel();

            const problem = this.validate(file);
            if (problem) {
                this.status = 'error';
                this.error = problem;
                return;
            }

            this.name = file.name;
            this.size = file.size;
            this.token = '';
            this.progress = 0;
            this.error = '';
            this.status = 'uploading';
            const info = await readVideoInfo(file);
            this.duration = info.duration ?? '';
            this.width = info.width ?? '';
            this.height = info.height ?? '';

            const controller = new AbortController();
            this.controller = controller;

            try {
                this.token = await uploadInChunks(file, config, (pct) => (this.progress = pct), controller.signal);
                this.progress = 100;
                this.status = 'done';
            } catch (error) {
                if (controller.signal.aborted) return;
                this.status = 'error';
                this.error = uploadErrorMessage(error);
            } finally {
                if (this.controller === controller) this.controller = null;
            }
        },

        onSubmit(event, videoRequired) {
            if (this.status === 'uploading') {
                event.preventDefault();
                return;
            }
            if (videoRequired && !this.token) {
                event.preventDefault();
                this.status = 'error';
                this.error = 'Selecione o arquivo de vídeo.';
                return;
            }
            this.submitting = true;
        },

        cancel() {
            this.controller?.abort();
            this.controller = null;
        },

        reset() {
            this.cancel();
            Object.assign(this, { status: 'idle', token: '', name: '', size: 0, duration: '', progress: 0, error: '' });
        },
    };
}
