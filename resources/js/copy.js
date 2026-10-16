/** Copia texto para a área de transferência (com alternativa para navegadores antigos). */
export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        document.execCommand('copy');
        field.remove();
    }
}

/** Botão "Copiar" das linhas da página de links. */
export function copyButton(text) {
    return {
        copied: false,
        async copy() {
            await copyText(text);
            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        },
    };
}
