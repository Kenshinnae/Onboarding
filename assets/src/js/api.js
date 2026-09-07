export async function submitOnboarding(form, files, sitemapFile) {
  const body = new FormData();
  body.append('payload', JSON.stringify(form));
  files.forEach((file) => body.append('assets[]', file));
  if (sitemapFile) body.append('sitemap', sitemapFile);
  const response = await fetch(window.clientOnboarding.endpoint, { method: 'POST', headers: { 'X-WP-Nonce': window.clientOnboarding.nonce }, body });
  const data = await response.json();
  if (!response.ok) { const error = new Error(data.message || 'Submission failed.'); error.fields = data.errors || {}; throw error; }
  return data;
}
