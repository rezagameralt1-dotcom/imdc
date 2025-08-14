module.exports = {
  testDir: "./e2e",
  timeout: 60000,
  retries: 0,
  use: { baseURL: process.env.PLAYWRIGHT_BASE_URL || "http://127.0.0.1:8099", trace: "off", headless: true },
};
