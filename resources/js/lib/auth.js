import { api } from "./api";

export async function csrf() {
  await api.get("/sanctum/csrf-cookie");
}

export async function login(email, password) {
  await csrf();
  const { data } = await api.post("/login-spa", { email, password });
  return data;
}

export async function me() {
  const { data } = await api.get("/me");
  return data;
}

export async function logout() {
  await api.post("/logout-spa");
}

