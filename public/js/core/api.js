// Seul endroit qui parle au serveur : ajoute le jeton CSRF et transforme les échecs en ApiError.

export class ApiError extends Error {}

const csrfToken = () => document.querySelector('meta[name=csrf]').content;

/** @returns {Promise<object>} le JSON de la réponse ; lève une ApiError au message affichable. */
export async function api(method, url, body) {
    let response;
    try {
        response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
            body: body ? JSON.stringify(body) : undefined,
        });
    } catch {
        throw new ApiError('Connexion impossible avec le serveur.');
    }

    if (response.status === 401) {
        location.href = '/login';
        throw new ApiError('Connexion requise.');
    }

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new ApiError(data.error ?? 'Une erreur est survenue, recharge la page.');
    }

    return data;
}
