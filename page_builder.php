<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require __DIR__.'/bootstrap.php';
require __DIR__.'/config.php';
require __DIR__.'/lib/db.php';
require __DIR__.'/lib/utils.php';
require __DIR__.'/admin_layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('require_admin')){
  function require_admin(){
    if (empty($_SESSION['admin_id'])) {
      header('Location: admin.php?route=login'); exit;
    }
  }
}
if (!function_exists('csrf_token')){
  function csrf_token(){ if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
}

require_admin();

$csrf = csrf_token();

admin_header('Editor da Home', false);
?>
<div class="max-w-[1600px] mx-auto px-4 py-6 space-y-4">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div>
      <h1 class="text-2xl font-bold mb-1">Editor visual da página inicial</h1>
      <p class="text-sm text-gray-500 max-w-2xl">Arraste blocos, edite textos inline e personalize estilos. Salve como rascunho para revisar depois ou publique para aplicar imediatamente na home.</p>
    </div>
    <div class="flex items-center gap-2">
      <button id="btn-preview" class="btn btn-ghost border border-gray-300 px-4 py-2 rounded-lg"><i class="fa-solid fa-eye mr-2"></i>Preview</button>
      <button id="btn-save" class="btn btn-primary px-4 py-2 rounded-lg bg-brand text-white"><i class="fa-solid fa-floppy-disk mr-2"></i>Salvar rascunho</button>
      <button id="btn-publish" class="btn px-4 py-2 rounded-lg bg-emerald-600 text-white"><i class="fa-solid fa-rocket mr-2"></i>Publicar</button>
    </div>
  </div>

  <div id="builder-alert" class="hidden px-4 py-3 rounded-lg text-sm"></div>

  <div class="builder-wrapper border border-gray-200 rounded-xl overflow-hidden">
    <div id="gjs" style="min-height: calc(100vh - 200px); background:#f5f5f5;"></div>
  </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.6/dist/css/grapes.min.css">
<script src="https://unpkg.com/grapesjs@0.21.6/dist/grapes.min.js"></script>
<script src="https://unpkg.com/grapesjs-blocks-basic@0.1.9/dist/grapesjs-blocks-basic.min.js"></script>

<script>
(function(){
  const API_URL = 'admin_api_layouts.php';
  const PAGE_SLUG = 'home';
  const CSRF_TOKEN = <?php echo json_encode($csrf, JSON_UNESCAPED_SLASHES); ?>;

  const alertBox = document.getElementById('builder-alert');
  function showMessage(msg, type='info') {
    alertBox.textContent = msg;
    alertBox.classList.remove('hidden','bg-emerald-100','bg-amber-100','bg-red-100','text-emerald-800','text-amber-800','text-red-800');
    if (type === 'success') {
      alertBox.classList.add('bg-emerald-100','text-emerald-800');
    } else if (type === 'warning') {
      alertBox.classList.add('bg-amber-100','text-amber-800');
    } else if (type === 'error') {
      alertBox.classList.add('bg-red-100','text-red-800');
    } else {
      alertBox.classList.add('bg-gray-100','text-gray-800');
    }
    alertBox.classList.remove('hidden');
    setTimeout(()=>alertBox.classList.add('hidden'), 8000);
  }

  const editor = grapesjs.init({
    container: '#gjs',
    height: '100%',
    fromElement: false,
    storageManager: false,
    plugins: ['gjs-blocks-basic'],
    pluginsOpts: {
      'gjs-blocks-basic': { flexGrid: true }
    },
    blockManager: { appendTo: '.gjs-pn-blocks-container' },
    selectorManager: { appendTo: '.gjs-sm-sectors' },
    styleManager: {
      appendTo: '.gjs-style-manager',
      sectors: [
        { name: 'Layout', open: true, buildProps: ['display','position','width','height','margin','padding'] },
        { name: 'Tipografia', open: false, buildProps: ['font-family','font-size','font-weight','letter-spacing','color','line-height','text-align'] },
        { name: 'Decoração', open: false, buildProps: ['background-color','background','border-radius','box-shadow'] }
      ]
    },
    canvas: {
      styles: [
        'https://cdn.tailwindcss.com'
      ]
    }
  });

  function addCustomBlocks(){
    const bm = editor.BlockManager;
    bm.add('hero-banner', {
      category: 'Seções',
      label: '<i class="fa-solid fa-image mr-2"></i>Hero Banner',
      content: `
        <section class="hero-section" style="padding:60px 20px;background:linear-gradient(135deg,#dc2626,#f59e0b);color:#fff;text-align:center;">
          <div style="max-width:700px;margin:0 auto;">
            <h1 style="font-size:42px;font-weight:700;margin-bottom:20px;">Título chamativo para sua campanha</h1>
            <p style="font-size:19px;opacity:.9;margin-bottom:30px;">Conte ao cliente o benefício principal da loja e adicione um call-to-action para o produto mais importante.</p>
            <a href="#" class="cta-btn" style="display:inline-block;padding:14px 28px;background:#fff;color:#dc2626;font-weight:600;border-radius:999px;text-decoration:none;">Comprar agora</a>
          </div>
        </section>
      `
    });
    bm.add('product-grid', {
      category: 'Seções',
      label: '<i class="fa-solid fa-table-cells mr-2"></i>Grade de Produtos',
      content: `
        <section style="padding:50px 20px;background:#f9fafb;">
          <div style="max-width:1100px;margin:0 auto;">
            <h2 style="font-size:32px;text-align:center;margin-bottom:24px;">Destaques da semana</h2>
            <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
              <div class="product-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;">
                <div style="height:160px;background:#f1f5f9;border-radius:12px;margin-bottom:16px;"></div>
                <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">Nome do Produto</h3>
                <p style="color:#475569;font-size:14px;margin-bottom:12px;">Descrição breve do produto e benefícios.</p>
                <strong style="font-size:20px;color:#dc2626;">$ 29,90</strong>
              </div>
              <div class="product-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;">
                <div style="height:160px;background:#f1f5f9;border-radius:12px;margin-bottom:16px;"></div>
                <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">Nome do Produto</h3>
                <p style="color:#475569;font-size:14px;margin-bottom:12px;">Descrição breve do produto e benefícios.</p>
                <strong style="font-size:20px;color:#dc2626;">$ 39,90</strong>
              </div>
              <div class="product-card" style="background:#fff;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center;">
                <div style="height:160px;background:#f1f5f9;border-radius:12px;margin-bottom:16px;"></div>
                <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">Nome do Produto</h3>
                <p style="color:#475569;font-size:14px;margin-bottom:12px;">Descrição breve do produto e benefícios.</p>
                <strong style="font-size:20px;color:#dc2626;">$ 19,90</strong>
              </div>
            </div>
          </div>
        </section>
      `
    });
    bm.add('testimonial', {
      category: 'Seções',
      label: '<i class="fa-solid fa-comment-dots mr-2"></i>Depoimentos',
      content: `
        <section style="padding:60px 20px;">
          <div style="max-width:900px;margin:0 auto;text-align:center;">
            <h2 style="font-size:32px;margin-bottom:30px;">O que nossos clientes dizem</h2>
            <div style="display:grid;gap:20px;">
              <blockquote style="background:#fff;border-radius:16px;padding:30px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
                <p style="font-style:italic;color:#475569;">“Excelente atendimento, entrega rápida e produtos de qualidade. Recomendo muito!”</p>
                <footer style="margin-top:18px;font-weight:600;">Maria Andrade — Fort Lauderdale</footer>
              </blockquote>
              <blockquote style="background:#fff;border-radius:16px;padding:30px;box-shadow:0 10px 40px rgba(15,23,42,.08);">
                <p style="font-style:italic;color:#475569;">“A loja online é super intuitiva e o suporte me ajudou rapidamente com minhas dúvidas.”</p>
                <footer style="margin-top:18px;font-weight:600;">João Silva — Orlando</footer>
              </blockquote>
            </div>
          </div>
        </section>
      `
    });
    bm.add('cta-section', {
      category: 'Seções',
      label: '<i class="fa-solid fa-bullhorn mr-2"></i>Chamada para ação',
      content: `
        <section style="padding:50px 20px;">
          <div style="max-width:900px;margin:0 auto;border-radius:24px;background:linear-gradient(135deg,#0ea5e9,#6366f1);padding:50px;color:#fff;text-align:center;">
            <h2 style="font-size:34px;font-weight:700;margin-bottom:16px;">Precisa de atendimento imediato?</h2>
            <p style="font-size:18px;opacity:.9;margin-bottom:24px;">Nosso time está pronto para ajudar você com recomendações personalizadas e envio rápido.</p>
            <a href="#" style="display:inline-flex;align-items:center;gap:10px;background:#fff;color:#0ea5e9;font-weight:600;padding:14px 28px;border-radius:999px;text-decoration:none;">Falar com um especialista</a>
          </div>
        </section>
      `
    });
  }
  addCustomBlocks();

  async function loadDraft(){
    try {
      const res = await fetch(`${API_URL}?action=get&page=${encodeURIComponent(PAGE_SLUG)}`, { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Falha ao carregar layout');
      if (data.draft && data.draft.content) {
        editor.setComponents(data.draft.content);
      }
      if (data.draft && data.draft.styles) {
        editor.setStyle(data.draft.styles);
      }
      if (data.published && !data.draft) {
        showMessage('Nenhum rascunho encontrado. Carregando versão publicada.', 'warning');
        editor.setComponents(data.published.content || '');
        editor.setStyle(data.published.styles || '');
      }
    } catch (err) {
      console.error(err);
      showMessage('Não foi possível carregar o layout: '+err.message, 'error');
    }
  }

  function getPayload(){
    return {
      page: PAGE_SLUG,
      content: editor.getHtml({ componentFirst: true }),
      styles: editor.getCss(),
      meta: {
        updated_by: '<?php echo sanitize_html($_SESSION['admin_email'] ?? 'admin'); ?>',
        updated_at: new Date().toISOString()
      },
      csrf: CSRF_TOKEN
    };
  }

  async function saveDraft(){
    showMessage('Salvando rascunho...', 'info');
    const payload = getPayload();
    try {
      const res = await fetch(API_URL+'?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Erro ao salvar');
      showMessage('Rascunho salvo com sucesso!', 'success');
    } catch (err) {
      showMessage('Erro ao salvar: '+err.message, 'error');
    }
  }

  async function publishDraft(){
    showMessage('Publicando alterações...', 'info');
    // garante que o que está no editor seja salvo primeiro
    await saveDraft();
    try {
      const res = await fetch(API_URL+'?action=publish', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ page: PAGE_SLUG, csrf: CSRF_TOKEN }),
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Erro ao publicar');
      showMessage('Página publicada! As mudanças já estão na home.', 'success');
    } catch (err) {
      showMessage('Erro ao publicar: '+err.message, 'error');
    }
  }

  function previewDraft(){
    const html = editor.getHtml({ componentFirst: true });
    const css = editor.getCss();
    const win = window.open('', '_blank');
    const doc = win.document;
    doc.open();
    doc.write(`
      <!doctype html>
      <html lang="pt-br">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Preview - Home personalizada</title>
        <style>${css}</style>
      </head>
      <body>${html}</body>
      </html>
    `);
    doc.close();
  }

  document.getElementById('btn-save').addEventListener('click', saveDraft);
  document.getElementById('btn-publish').addEventListener('click', publishDraft);
  document.getElementById('btn-preview').addEventListener('click', previewDraft);

  loadDraft();
})();
</script>
<?php
admin_footer();
