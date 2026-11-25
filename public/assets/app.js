// Browser-side validation for forms marked data-validate: shows each field's message
// beside it as the user types and on submit. The server always validates again.
(() => {
    'use strict';

    // Our own messages instead of the browser's vague ones ("Enter a valid value"):
    // data-invalid explains the rule, data-max-message covers going over the maximum.
    const message = (input) => {
        // data-same-as="password": the confirmation must match that field.
        const same = input.dataset.sameAs && input.form.elements.namedItem(input.dataset.sameAs);
        input.setCustomValidity(same && input.value !== '' && input.value !== same.value ? 'mismatch' : '');
        if (input.hasAttribute('data-strong-password') && input.value !== '') {
            const value = input.value;
            const strong = Array.from(value).length >= 8 && new TextEncoder().encode(value).length <= 72
                && /[A-Z]/.test(value) && /[a-z]/.test(value) && /[0-9]/.test(value)
                && /[^A-Za-z0-9\s]/u.test(value) && !value.includes('\0');
            input.setCustomValidity(strong ? '' : 'weak password');
        }

        const validity = input.validity;
        if (validity.valid) {
            return '';
        }
        const label = (input.labels?.[0]?.textContent ?? 'This field').replace(/\s*\(.*\)\s*$/, '').trim();
        if (validity.valueMissing && !validity.badInput) {
            return `${label} is required.`;
        }
        if (validity.rangeOverflow && input.dataset.maxMessage) {
            return input.dataset.maxMessage;
        }

        return input.dataset.invalid ?? input.validationMessage;
    };

    const show = (input) => {
        const text = message(input);
        const slot = input.form.querySelector(`[data-error-for="${input.name}"]`);
        if (slot) {
            slot.textContent = text;
        }
        input.setAttribute('aria-invalid', text === '' ? 'false' : 'true');
        input.classList.toggle('border-red-400', text !== '');
        input.classList.toggle('border-slate-300', text === '');

        return text === '';
    };

    document.querySelectorAll('form[data-validate]').forEach((form) => {
        const inputs = [...form.querySelectorAll('input[name]:not([type="hidden"])')];
        inputs.forEach((input) => input.addEventListener('input', () => show(input)));

        form.addEventListener('submit', (event) => {
            const valid = inputs.map(show).every(Boolean);
            if (!valid) {
                event.preventDefault();
                inputs.find((input) => !input.validity.valid)?.focus();
            }
        });
    });

    // Number fields with min >= 0 (price, stock, quantity) can't take a minus sign or an
    // exponent; whole-number fields (step="1") can't take a decimal point either.
    document.querySelectorAll('input[type="number"]').forEach((input) => {
        if (Number(input.min) < 0 || input.min === '') {
            return;
        }
        const blocked = input.step === '1' ? ['-', '+', 'e', 'E', '.', ','] : ['-', '+', 'e', 'E'];

        input.addEventListener('keydown', (event) => {
            if (blocked.includes(event.key)) {
                event.preventDefault();
            }
        });
        input.addEventListener('paste', (event) => {
            const text = event.clipboardData?.getData('text') ?? '';
            if (blocked.some((character) => text.includes(character))) {
                event.preventDefault();
            }
        });
    });

    // Money like the server's View::money(): thousands separators, 2 to 3 decimals
    // (6878.115 → "6,878.115", 3.990 → "3.99"). Exact integer thousandths, no floats.
    const money = (thousandths) => {
        const whole = (thousandths / 1000n).toLocaleString('en-US');
        const fraction = (thousandths % 1000n).toString().padStart(3, '0').replace(/0+$/, '').padEnd(2, '0');

        return `${whole}.${fraction}`;
    };

    // Live total on the purchase form: quantity × price.
    document.querySelectorAll('input[data-price]').forEach((input) => {
        const total = input.form.querySelector('[data-total]');
        const [whole, fraction = ''] = input.dataset.price.split('.');
        const thousandths = BigInt(whole) * 1000n + BigInt(fraction.padEnd(3, '0').slice(0, 3));
        const update = () => {
            if (total && /^[1-9][0-9]{0,9}$/.test(input.value)) {
                total.textContent = `$${money(thousandths * BigInt(input.value))}`;
            }
        };
        input.addEventListener('input', update);
        update();
    });

    // Forms marked data-require-change (the role picker): Save stays disabled until a
    // different option is chosen, so an unchanged form is never sent. The server checks again.
    document.querySelectorAll('form[data-require-change]').forEach((form) => {
        const fieldset = form.querySelector('fieldset');
        if (fieldset?.disabled) {
            return;
        }
        const button = form.querySelector('button[type="submit"]');
        const hint = form.querySelector('[data-change-hint]');
        const serialize = () => new URLSearchParams(new FormData(form)).toString();
        const initial = serialize();
        const changed = () => serialize() !== initial;
        const update = () => {
            button.disabled = !changed();
            hint?.classList.toggle('hidden', changed());
        };
        form.addEventListener('change', update);
        form.addEventListener('submit', (event) => {
            if (!changed()) {
                event.preventDefault();
                update();
            }
        });
        update();
    });

    // Login cooldown countdown. The server counts wrong passwords in the session and refuses
    // logins for a while (429); the page tells us how many seconds, and we disable Log in and
    // count down until then. The server enforces it, so this is only the display.
    const loginForm = document.querySelector('form[data-login-retry-after]');
    const cooldown = document.querySelector('[data-login-cooldown]');
    if (loginForm && cooldown) {
        const until = Date.now() + Number(loginForm.dataset.loginRetryAfter) * 1000;
        const button = loginForm.querySelector('button[type="submit"]');
        const tick = () => {
            const seconds = Math.ceil((until - Date.now()) / 1000);
            button.disabled = seconds > 0;
            if (seconds > 0) {
                cooldown.textContent = `Too many failed logins. Try again in ${seconds} second${seconds === 1 ? '' : 's'}.`;
                setTimeout(tick, 1000);
            } else {
                cooldown.textContent = 'You can try again now.';
                cooldown.classList.replace('bg-red-50', 'bg-emerald-50');
                cooldown.classList.replace('text-red-700', 'text-emerald-700');
            }
        };
        tick();
    }

    // Ask before destructive actions such as deleting a product.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
})();
