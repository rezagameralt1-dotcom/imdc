import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
  server: {
    host: "digitalcity.test",
    port: 5173,
    strictPort: true,
    hmr: { host: "digitalcity.test" },
  },
  plugins: [
    laravel({
      input: ["resources/js/app.js"],
      refresh: true,
    }),
  ],
});

