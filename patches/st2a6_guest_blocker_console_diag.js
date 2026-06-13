/* ST-2a.6 — guest checkout blocker live diagnostic (READ-ONLY, no changes).
 * Run in anonymous tab on index.php?route=checkout/checkout with a product in cart.
 * Paste in DevTools Console, press Enter, THEN reproduce the guest flow.
 * Goal: prove whether checkout/register.save (a) fires at all, and (b) returns error.captcha.
 * Root cause already confirmed statically: reCAPTCHA v2_checkbox enabled on 'register' page,
 * not rendered in checkout.twig -> guest autosave has no g-recaptcha-response -> save fails.
 * This snippet is the live confirmation before patching.
 */
(function () {
  var $ = window.jQuery;
  if (!$) { console.error('[BS-diag] jQuery not found on page'); return; }
  function rt(s) { return String(s == null ? '' : s).replace(/\s+/g, ' ').trim(); }

  var f = $('#form-register');
  console.group('%c[BS-diag] guest-blocker', 'font-weight:bold;color:#06c');
  console.log('#form-register present:', f.length > 0);

  // (1) registerIsComplete() mirror — tells us if the autosave POST will fire
  var npOk = (typeof window.bsCheckoutNpIsComplete === 'function') ? window.bsCheckoutNpIsComplete() : '(fn missing)';
  var phone = $('#input-telephone');
  var agree = $('#input-register-agree');
  var cond = {
    npComplete: npOk,
    firstOk: !!rt($('#input-firstname').val()),
    lastOk: !!rt($('#input-lastname').val()),
    emailOk: /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(rt($('#input-email').val())),
    phoneFieldExists: phone.length > 0,
    phoneOk: !!(phone.length && rt(phone.val()).length >= 3),
    passwordOk: !$('#input-register').prop('checked') || rt($('#input-password').val()).length > 0,
    agreeFieldExists: agree.length > 0,
    agreeOk: !agree.length || agree.closest('#register-agree').hasClass('d-none') || agree.prop('checked')
  };
  cond.WILL_FIRE = !!(f.length && npOk === true && cond.firstOk && cond.lastOk &&
    cond.emailOk && cond.phoneOk && cond.passwordOk && cond.agreeOk);
  console.table(cond);

  // (2) payload that would be sent — confirm NO captcha token, check zone/address/account
  var ser = f.length ? f.serialize() : '';
  console.log('payload serialize():', ser);
  console.log('has g-recaptcha-response in payload:', /g-recaptcha-response/.test(ser));
  console.log('grecaptcha global present:', typeof window.grecaptcha);
  console.log('account=', (ser.match(/(?:^|&)account=([^&]*)/) || [])[1],
    '| shipping_zone_id=', (ser.match(/shipping_zone_id=([^&]*)/) || [])[1],
    '| shipping_address_1 set:', /shipping_address_1=[^&]+/.test(ser));

  // (3) hook the actual register.save response (ajaxComplete fires on any HTTP status)
  $(document).ajaxComplete(function (e, xhr, settings) {
    var u = (settings && settings.url) ? settings.url : '';
    if (u.indexOf('checkout/register.save') === -1) return;
    var body; try { body = JSON.parse(xhr.responseText); } catch (_) { body = xhr.responseText; }
    console.group('%c[BS-diag] register.save RESPONSE', 'color:#c00;font-weight:bold');
    console.log('HTTP:', xhr.status);
    console.log('sent data:', settings.data);
    console.log('response:', body);
    if (body && body.error) {
      console.warn('ERROR KEYS:', Object.keys(body.error));
      console.warn('error.captcha =', body.error.captcha);
    } else if (body && body.success) {
      console.log('SUCCESS — blocker is elsewhere (not register.save)');
    }
    console.groupEnd();
  });

  console.log('%cHook armed. Now reproduce the guest flow (email, NP area/city/warehouse via dropdown, phone). Watch for "register.save RESPONSE".', 'color:#080');
  console.groupEnd();
})();
