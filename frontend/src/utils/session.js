const storageKey = 'sheap-shopping.session';

export const getSession = () => {
    try {
        const session = JSON.parse(localStorage.getItem(storageKey) || 'null');
        if (!session || !['admin', 'cliente'].includes(session.rol) || !session.id || !session.token) return null;
        return session;
    } catch {
        localStorage.removeItem(storageKey);
        return null;
    }
};

export const saveSession = (user, token) => {
    const id = Number(user?.id_usuario ?? user?.id);
    if (!user || !id || !['admin', 'cliente'].includes(user.rol) || !token) {
        throw new Error('La respuesta de autenticación no contiene un usuario o token válido.');
    }
    const session = {
        id,
        nombre: String(user.nombre || ''),
        correo: String(user.correo || user.email || ''),
        rol: user.rol,
        token: String(token),
    };
    localStorage.setItem(storageKey, JSON.stringify(session));
    return session;
};

export const clearSession = () => localStorage.removeItem(storageKey);
