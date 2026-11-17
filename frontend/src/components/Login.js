import { api } from '../utils/api.js';
import { clearSession, saveSession } from '../utils/session.js';

export const initLogin = (onAuthenticated) => {
    const form = document.getElementById('LoginForm');
    const message = document.getElementById('LoginMessage');
    const submit = document.getElementById('LoginSubmit');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        message.classList.add('hidden');
        submit.disabled = true;
        submit.textContent = 'Verificando...';

        try {
            const response = await api.post('auth/login', {
                username: document.getElementById('LoginUsername').value.trim(),
                password: document.getElementById('LoginPassword').value,
            });
            const user = saveSession(response?.usuario);
            if (user.rol === 'estudiante') {
                clearSession();
                throw new Error('Este panel está habilitado solo para administradores y docentes.');
            }
            form.reset();
            await onAuthenticated(user);
        } catch (error) {
            message.textContent = error.message || 'No se pudo iniciar sesión.';
            message.className = 'mt-4 rounded-md bg-red-50 px-4 py-3 text-sm font-semibold text-red-700';
        } finally {
            submit.disabled = false;
            submit.textContent = 'Entrar';
        }
    });
};
