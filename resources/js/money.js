/**
 * Máscara de reais: qualquer <input data-money>. Digitar 4990 mostra 49,90;
 * 123456 mostra 1.234,56. O servidor converte o texto para centavos.
 */
function formatMoney(input) {
    const digits = input.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 11);
    if (!digits) {
        input.value = '';
        return;
    }
    const cents = parseInt(digits, 10);
    input.value = (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Fase de captura: formata ANTES de outros ouvintes (ex.: x-model do Alpine) lerem o valor.
document.addEventListener(
    'input',
    (event) => {
        if (event.target.matches?.('[data-money]')) formatMoney(event.target);
    },
    true,
);
