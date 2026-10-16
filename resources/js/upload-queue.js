import axios from 'axios';
import { formatBytes, readVideoInfo, uploadErrorMessage, uploadInChunks } from './chunk-upload';

let nextKey = 1;

function emptyFilm() {
    return {
        key: nextKey++,
        title: '',
        description: '',
        genre: '',
        age_rating: '',
        price: '',
        coverFile: null,
        coverUrl: null,
        videoFile: null,
        duration: '',
        video_width: '',
        video_height: '',
        token: '', // upload do vídeo já concluído (não precisa subir de novo se só faltar corrigir campos)
        status: 'pending', // pending | uploading | saving | done | error
        progress: 0,
        error: '',
        errors: {},
        fixedUrl: '',
        copied: false,
    };
}

/**
 * Fila de cadastro (Alpine: x-data="uploadQueue({...})"): vários filmes no mesmo formulário,
 * enviados um de cada vez ao clicar em "Carregar todos os filmes".
 * Cada filme: vídeo em partes (/uploads/chunk) e depois os dados + capa (POST /videos, JSON).
 */
export function uploadQueue(config) {
    return {
        films: [emptyFilm()],
        running: false,
        finished: false,
        copiedAll: false,

        init() {
            window.addEventListener('beforeunload', (event) => {
                if (this.running) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },

        get pending() {
            return this.films.filter((f) => f.status !== 'done');
        },

        get done() {
            return this.films.filter((f) => f.status === 'done');
        },

        /** Bytes de vídeo que ainda vão subir neste envio. */
        get totalBytes() {
            return this.pending.reduce((sum, f) => sum + (f.token ? 0 : f.videoFile?.size ?? 0), 0);
        },

        get overLimit() {
            return this.totalBytes > config.batchMaxBytes;
        },

        get totalLabel() {
            return formatBytes(this.totalBytes);
        },

        get overallProgress() {
            const items = this.pending.length + this.done.length;
            if (!items) return 0;
            const sum = this.films.reduce((acc, f) => acc + (f.status === 'done' ? 100 : f.progress), 0);
            return Math.round(sum / this.films.length);
        },

        sizeLabel(bytes) {
            return formatBytes(bytes);
        },

        addFilm() {
            this.films.push(emptyFilm());
            this.$nextTick(() => document.getElementById(`film-${this.films.at(-1).key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },

        removeFilm(film) {
            if (film.coverUrl) URL.revokeObjectURL(film.coverUrl);
            this.films = this.films.filter((f) => f !== film);
            if (!this.films.length) this.films.push(emptyFilm());
        },

        pickCover(film, event) {
            const file = event.target.files[0];
            if (!file) return;
            if (film.coverUrl) URL.revokeObjectURL(film.coverUrl);
            film.coverFile = file;
            film.coverUrl = URL.createObjectURL(file);
            delete film.errors.cover;
        },

        async pickVideo(film, event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;

            const extension = file.name.split('.').pop().toLowerCase();
            if (!config.extensions.includes(extension)) {
                film.errors = { ...film.errors, video: `Formato não suportado. Use: ${config.extensions.join(', ').toUpperCase()}.` };
                return;
            }
            if (file.size > config.maxBytes) {
                film.errors = { ...film.errors, video: `O vídeo excede o limite de ${config.maxLabel}.` };
                return;
            }

            film.videoFile = file;
            film.token = '';
            film.progress = 0;
            film.errors = { ...film.errors, video: null };
            const info = await readVideoInfo(file);
            film.duration = info.duration ?? '';
            film.video_width = info.width ?? '';
            film.video_height = info.height ?? '';
        },

        /** Confere os campos obrigatórios antes de começar (sem gastar upload à toa). */
        validate(film) {
            const errors = {};
            if (!film.title.trim()) errors.title = 'Informe o título.';
            if (!film.genre) errors.genre = 'Escolha o tipo.';
            if (!film.age_rating) errors.age_rating = 'Escolha a classificação.';
            if (!film.coverFile) errors.cover = 'Escolha a capa.';
            if (!film.videoFile && !film.token) errors.video = 'Escolha o arquivo de vídeo.';
            film.errors = errors;
            return !Object.keys(errors).length;
        },

        async uploadAll() {
            if (this.running || this.overLimit) return;

            const queue = this.pending;
            const invalid = queue.filter((film) => !this.validate(film));
            if (invalid.length) {
                invalid.forEach((film) => (film.status = 'error'));
                invalid[0] && document.getElementById(`film-${invalid[0].key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            this.running = true;
            for (const film of queue) {
                await this.uploadOne(film);
            }
            this.running = false;
            this.finished = this.pending.length === 0;
            if (this.finished) this.$nextTick(() => document.getElementById('queue-results')?.scrollIntoView({ behavior: 'smooth' }));
        },

        async uploadOne(film) {
            film.error = '';
            film.errors = {};

            try {
                if (!film.token) {
                    film.status = 'uploading';
                    film.token = await uploadInChunks(film.videoFile, config, (pct) => (film.progress = pct));
                }

                film.status = 'saving';
                film.progress = 100;
                const form = new FormData();
                for (const field of ['title', 'description', 'genre', 'age_rating', 'price', 'duration', 'video_width', 'video_height']) {
                    form.append(field, film[field] ?? '');
                }
                form.append('video_token', film.token);
                form.append('cover', film.coverFile);

                const { data } = await axios.post(config.storeUrl, form, { headers: { Accept: 'application/json' } });
                film.fixedUrl = data.fixed_url;
                film.status = 'done';
            } catch (error) {
                film.status = 'error';
                if (error.response?.status === 422) {
                    const errors = error.response.data.errors ?? {};
                    film.errors = Object.fromEntries(Object.entries(errors).map(([k, v]) => [k === 'video_token' ? 'video' : k, v[0]]));
                    film.error = 'Corrija os campos destacados e clique em carregar de novo.';
                    if (errors.video_token) film.token = ''; // upload expirou: precisa subir o vídeo de novo
                } else {
                    film.error = uploadErrorMessage(error);
                }
            }
        },

        async copy(text, target) {
            try {
                await navigator.clipboard.writeText(text);
            } catch {
                const field = Object.assign(document.createElement('textarea'), { value: text });
                document.body.appendChild(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }
            target.copied = true;
            setTimeout(() => (target.copied = false), 2000);
        },

        copyAll() {
            const text = this.done.map((f) => `${f.title}: ${f.fixedUrl}`).join('\n');
            this.copy(text, this);
        },

        // "copied" do botão Copiar todos
        get copied() {
            return this.copiedAll;
        },
        set copied(value) {
            this.copiedAll = value;
        },

        restart() {
            this.films.forEach((f) => f.coverUrl && URL.revokeObjectURL(f.coverUrl));
            this.films = [emptyFilm()];
            this.finished = false;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    };
}
