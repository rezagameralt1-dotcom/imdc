import { api } from "./api";

// نیازی به csrf نیست (اندپوینت‌ها CSRF-exempt هستند)

export async function login(email, password) {
  const { data } = await api.post("/spa-auth/login", { email, password });
  return data; // { ok: true, user: {...} } یا خطای 422
}

export async function me() {
  const { data } = await api.get("/spa-auth/me");
  return data; // { ok: true/false, user: {...}|null }
}

export async function logout() {
  const { data } = await api.post("/spa-auth/logout");
  return data; // { ok: true }
}
