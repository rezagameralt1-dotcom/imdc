import axios from "axios";

export const api = axios.create({
  baseURL: "http://digitalcity.test",
  withCredentials: true,
  xsrfCookieName: "XSRF-TOKEN",
  xsrfHeaderName: "X-XSRF-TOKEN",
});

api.interceptors.response.use(
  (r) => r,
  (err) => {
    const s = err.response?.status;
    if (s === 419) console.warn("CSRF token mismatch");
    if (s === 401) console.warn("Unauthenticated");
    return Promise.reject(err);
  }
);

