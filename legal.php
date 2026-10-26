<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require_login();

$gen = null;
$errors = [];
$input = $_SESSION['legal_form'] ?? ['lang' => 'ca', 'purposes' => ['contacto'], 'cookies' => ['ga4'], 'jurisdiction' => 'es', 'cmp' => 'cookieyes'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $input = $_POST;
    unset($input['_csrf']);
    $gen = new LegalGenerator($input);
    $errors = $gen->errors();
    $_SESSION['legal_form'] = $input;
    if ($errors) {
        $gen = null;
    }
}

$v = static fn (string $k) => e($input[$k] ?? '');
$checked = static fn (string $k, string $val) => in_array($val, (array) ($input[$k] ?? []), true) ? 'checked' : '';
$sel = static fn (string $k, string $val) => ($input[$k] ?? '') === $val ? 'selected' : '';

layout_start('Textos legals', 'legal');
?>
<?php if ($errors): ?>
  <div class="alert alert-error"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<?php if ($gen): ?>
<section class="card legal-output" id="resultat">
  <div class="tabs" role="tablist">
    <button type="button" class="active" data-tab="privacy">Política de privadesa</button>
    <button type="button" data-tab="cookies">Política de cookies</button>
    <?php if ($gen->isShop()): ?>
      <button type="button" data-tab="returns">Cancel·lació i devolucions</button>
    <?php endif; ?>
    <span class="tab-lang"><?= e(LegalGenerator::LANGS[$gen->lang()]) ?></span>
  </div>
  <div class="legal-toolbar">
    <button type="button" class="btn btn-ghost btn-sm" data-copy-html><?= icon('copy') ?> Copiar HTML</button>
    <button type="button" class="btn btn-ghost btn-sm" data-copy-text><?= icon('copy') ?> Copiar text</button>
    <button type="button" class="btn btn-primary btn-sm" data-download><?= icon('pdf') ?> Descarregar .html</button>
  </div>
  <article class="legal-doc" lang="<?= e($gen->lang()) ?>" data-panel="privacy" data-filename="<?= e($gen->slug('privacy')) ?>"><?= $gen->privacy() ?></article>
  <article class="legal-doc" lang="<?= e($gen->lang()) ?>" data-panel="cookies" data-filename="<?= e($gen->slug('cookies')) ?>" hidden><?= $gen->cookies() ?></article>
  <?php if ($gen->isShop()): ?>
    <article class="legal-doc" lang="<?= e($gen->lang()) ?>" data-panel="returns" data-filename="<?= e($gen->slug('returns')) ?>" hidden><?= $gen->returns() ?></article>
  <?php endif; ?>
  <p class="disclaimer">⚠️ Text orientatiu generat automàticament. Revisa’l i adapta’l a l’activitat real del lloc web; en cas de dubte, consulta un professional en protecció de dades. Comprova les cookies reals amb un escàner (p. ex. el de la teva eina de consentiment).</p>
</section>
<?php endif; ?>

<form class="card legal-form" method="post" action="legal.php#resultat">
  <?= csrf_field() ?>
  <h2>Idioma i legislació</h2>
  <div class="grid-2">
    <label>Idioma dels textos
      <select name="lang">
        <?php foreach (LegalGenerator::LANGS as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= $sel('lang', $code) ?>><?= e(strtoupper($code) . ' · ' . $label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Legislació aplicable
      <select name="jurisdiction">
        <option value="es" <?= $sel('jurisdiction', 'es') ?>>Espanya (RGPD + LOPDGDD + LSSI-CE)</option>
        <option value="eu" <?= $sel('jurisdiction', 'eu') ?>>Unió Europea (RGPD genèric)</option>
      </select>
    </label>
  </div>

  <h2>Dades del titular</h2>
  <div class="grid-2">
    <label>Tipus de titular
      <select name="owner_type">
        <option value="empresa" <?= $sel('owner_type', 'empresa') ?>>Empresa / societat</option>
        <option value="autonomo" <?= $sel('owner_type', 'autonomo') ?>>Autònom / persona física</option>
      </select>
    </label>
    <label>Nom o raó social *<input name="name" value="<?= $v('name') ?>" required placeholder="Exemple Digital, SL"></label>
    <label>Nom comercial<input name="trade_name" value="<?= $v('trade_name') ?>" placeholder="Exemple"></label>
    <label>NIF / CIF *<input name="nif" value="<?= $v('nif') ?>" required placeholder="B12345678"></label>
    <label>Lloc web *<input name="website" value="<?= $v('website') ?>" required placeholder="https://www.exemple.cat"></label>
    <label>Telèfon<input name="phone" value="<?= $v('phone') ?>" placeholder="+34 600 000 000"></label>
    <label class="span-2">Domicili *<input name="address" value="<?= $v('address') ?>" required placeholder="Carrer Major 1, 08001 Barcelona"></label>
    <label>Correu de contacte / privadesa *<input type="email" name="email" value="<?= $v('email') ?>" required placeholder="privadesa@exemple.cat"></label>
    <label>Correu del DPD/DPO <span class="muted">(si n’hi ha)</span><input type="email" name="dpo" value="<?= $v('dpo') ?>" placeholder="dpd@exemple.cat"></label>
    <label class="span-2">Dades registrals<input name="registry" value="<?= $v('registry') ?>" placeholder="Registre Mercantil de Barcelona, Tom…, Foli…, Full…"></label>
  </div>

  <h2>Quines dades personals recull el web?</h2>
  <div class="checks-grid">
    <?php foreach (LegalGenerator::PURPOSES as $key => $label): ?>
      <label class="check-opt"><input type="checkbox" name="purposes[]" value="<?= e($key) ?>" <?= $checked('purposes', $key) ?>> <?= e($label) ?></label>
    <?php endforeach; ?>
  </div>

  <h2>Botiga en línia: cancel·lació i devolucions</h2>
  <p class="muted small">Si marques «Botiga en línia / comandes i pagaments» es genera també la Política de cancel·lació, desistiment i devolucions.</p>
  <div class="grid-2">
    <label>Termini de desistiment (dies)<input type="number" name="ret_days" min="14" max="365" value="<?= $v('ret_days') ?: '14' ?>"></label>
    <label>Cost de la devolució
      <select name="ret_costs">
        <option value="client" <?= $sel('ret_costs', 'client') ?>>A càrrec del client</option>
        <option value="shop" <?= $sel('ret_costs', 'shop') ?>>A càrrec de la botiga (devolució gratuïta)</option>
      </select>
    </label>
    <label>Cancel·lació abans de l’enviament
      <select name="ret_cancel">
        <option value="yes" <?= $sel('ret_cancel', 'yes') ?>>Permesa sense cost</option>
        <option value="no" <?= $sel('ret_cancel', 'no') ?>>No permesa (només desistiment)</option>
      </select>
    </label>
    <label>Correu per a devolucions <span class="muted">(si és diferent)</span><input type="email" name="ret_email" value="<?= $v('ret_email') ?>" placeholder="devolucions@exemple.cat"></label>
    <label class="span-2">Adreça de devolució <span class="muted">(si és diferent del domicili)</span><input name="ret_address" value="<?= $v('ret_address') ?>" placeholder="Magatzem: Polígon Industrial…, 08400 Granollers"></label>
    <label class="span-2">URL de la funció de desistiment <span class="muted">(botó «Desistir del contracte», obligatori des del 19/06/2026 — Directiva (UE) 2023/2673)</span><input name="ret_url" value="<?= $v('ret_url') ?>" placeholder="https://www.exemple.cat/desistiment"></label>
  </div>
  <p class="muted small">Productes exclosos del dret de desistiment que ven la botiga:</p>
  <div class="checks-grid">
    <?php foreach (LegalGenerator::RETURN_EXCLUSIONS as $key => $label): ?>
      <label class="check-opt"><input type="checkbox" name="ret_exclusions[]" value="<?= e($key) ?>" <?= $checked('ret_exclusions', $key) ?>> <?= e($label) ?></label>
    <?php endforeach; ?>
    <label class="check-opt"><input type="checkbox" name="ret_exchange" value="1" <?= ($input['ret_exchange'] ?? '') === '1' ? 'checked' : '' ?>> Accepto canvis de talla, color o model</label>
  </div>

  <h2>Proveïdors (encarregats del tractament)</h2>
  <div class="grid-2">
    <label>Allotjament<input name="hosting" value="<?= $v('hosting') ?>" placeholder="Raiola Networks, SiteGround, Hostinger…"></label>
    <label>Màrqueting per correu<input name="email_tool" value="<?= $v('email_tool') ?>" placeholder="Mailchimp, Brevo, MailerLite…"></label>
    <label>Passarel·la de pagament<input name="payment" value="<?= $v('payment') ?>" placeholder="Stripe, PayPal, Redsys…"></label>
    <label>Altres <span class="muted">(un per línia)</span><textarea name="other_processors" rows="2" placeholder="Google Workspace (correu)&#10;Holded (facturació)"><?= $v('other_processors') ?></textarea></label>
  </div>

  <h2>Cookies i serveis de tercers</h2>
  <div class="grid-2">
    <label>Eina de consentiment (bàner)
      <select name="cmp">
        <?php foreach (LegalGenerator::CMPS as $key => [$label]): ?>
          <option value="<?= e($key) ?>" <?= $sel('cmp', $key) ?>><?= e($label ?? 'Una altra / pròpia') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="checks-grid">
    <?php foreach (LegalGenerator::COOKIE_SERVICES as $key => $label): ?>
      <label class="check-opt"><input type="checkbox" name="cookies[]" value="<?= e($key) ?>" <?= $checked('cookies', $key) ?>> <?= e($label) ?></label>
    <?php endforeach; ?>
  </div>
  <label>Cookies addicionals <span class="muted">(una per línia: nom | titular | finalitat | durada | tipus — escriu-les en l’idioma dels textos)</span>
    <textarea name="custom_cookies" rows="3" placeholder="_hsp | HubSpot | Preferències de privadesa | 13 mesos | Tècnica"><?= $v('custom_cookies') ?></textarea>
  </label>

  <div class="form-foot">
    <p class="muted small">Els camps amb * són obligatoris. Les dades només es desen a la teva sessió.</p>
    <button class="btn btn-primary" type="submit"><?= icon('legal') ?> Generar textos</button>
  </div>
</form>
<?php layout_end();
