(() => {
  'use strict';

  // Menú lateral en mòbil
  const sidebar = document.getElementById('sidebar');
  document.querySelector('[data-toggle-sidebar]')?.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebar?.classList.toggle('open');
  });
  document.addEventListener('click', (e) => {
    if (sidebar?.classList.contains('open') && !sidebar.contains(e.target)) sidebar.classList.remove('open');
  });

  // Pantalla de càrrega durant les anàlisis
  const loading = document.getElementById('loading');
  document.querySelectorAll('form[data-loading]').forEach((form) => {
    form.addEventListener('submit', () => {
      const lines = (form.querySelector('textarea')?.value || '').split(/[\s,;]+/).filter(Boolean).length;
      document.getElementById('loading-title').textContent = form.dataset.loading;
      document.getElementById('loading-text').textContent =
        `${lines} URL · uns ${Math.max(5, lines * 4)} segons aprox. No tanquis la pàgina.`;
      loading.hidden = false;
      form.querySelector('button[type=submit]').disabled = true;
    });
  });

  // Filtres de comprovacions
  document.querySelectorAll('[data-filters]').forEach((bar) => {
    const list = bar.nextElementSibling;
    bar.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-filter]');
      if (!btn) return;
      bar.querySelectorAll('button').forEach((b) => b.classList.toggle('active', b === btn));
      list.querySelectorAll('.check').forEach((li) => {
        li.hidden = btn.dataset.filter !== 'all' && li.dataset.status !== btn.dataset.filter;
      });
    });
  });

  // Copiar al porta-retalls
  const flashBtn = (btn, text = 'Copiat!') => {
    const original = btn.innerHTML;
    btn.textContent = text;
    setTimeout(() => (btn.innerHTML = original), 1400);
  };
  const copy = async (text, btn) => {
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      const ta = Object.assign(document.createElement('textarea'), { value: text });
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
    }
    flashBtn(btn);
  };
  document.querySelectorAll('[data-copy]').forEach((btn) =>
    btn.addEventListener('click', () => copy(document.querySelector(btn.dataset.copy).textContent, btn)));

  // Textos legals: pestanyes, copiar i descarregar
  const panels = document.querySelectorAll('[data-panel]');
  const activePanel = () => [...panels].find((p) => !p.hidden);
  document.querySelectorAll('[data-tab]').forEach((tab) =>
    tab.addEventListener('click', () => {
      document.querySelectorAll('[data-tab]').forEach((t) => t.classList.toggle('active', t === tab));
      panels.forEach((p) => (p.hidden = p.dataset.panel !== tab.dataset.tab));
    }));
  document.querySelector('[data-copy-html]')?.addEventListener('click', (e) => copy(activePanel().innerHTML.trim(), e.currentTarget));
  document.querySelector('[data-copy-text]')?.addEventListener('click', (e) => copy(activePanel().innerText.trim(), e.currentTarget));
  document.querySelector('[data-download]')?.addEventListener('click', () => {
    const panel = activePanel();
    const title = panel.querySelector('h1')?.textContent || 'Document legal';
    const html = `<!doctype html>
<html lang="${panel.lang || 'ca'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${title}</title>
<style>body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;max-width:860px;margin:40px auto;padding:0 20px;line-height:1.6;color:#1f1e2e}
h1{font-size:28px}h2{font-size:20px;margin-top:1.6em}h3{font-size:16px}table{border-collapse:collapse;width:100%;font-size:14px}
th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;vertical-align:top}th{background:#f5f5f8}.legal-updated{color:#666}
blockquote{margin:1em 0;padding:12px 18px;border-left:3px solid #ddd;background:#f5f5f8}</style>
</head><body>
${panel.innerHTML.trim()}
</body></html>`;
    const a = Object.assign(document.createElement('a'), {
      href: URL.createObjectURL(new Blob([html], { type: 'text/html' })),
      download: `${panel.dataset.filename}.html`,
    });
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
  });
})();
