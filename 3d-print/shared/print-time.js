/*
 * Canonical print-time boundary parser for the owner dashboard and Serhiy's
 * local server. The bound Apps Script mirrors this small, dependency-free
 * contract because Apps Script cannot import repository files at runtime.
 */
(function (root) {
  'use strict';

  var MIN_HOURS = 0.02;
  var MAX_HOURS = 100;

  function normaliseHours(hours) {
    return Number(Number(hours).toFixed(10));
  }

  function blankResult() {
    return { ok: true, blank: true, kind: 'blank', hours: null };
  }

  function invalidResult() {
    return {
      ok: false,
      blank: false,
      kind: 'invalid',
      error: 'Введіть десяткові години (1,65), 1:39 або 1 год 39 хв.',
    };
  }

  function parsedResult(hours, kind) {
    if (!Number.isFinite(hours) || hours < 0) return invalidResult();
    return { ok: true, blank: false, kind: kind, hours: normaliseHours(hours) };
  }

  function parse(value) {
    if (value === null || typeof value === 'undefined') return blankResult();
    var raw = String(value).replace(/\u00a0/g, ' ').trim();
    if (!raw) return blankResult();
    var text = raw.toLowerCase().replace(/\s+/g, ' ');
    var clock = /^(\d+):([0-5]?\d)(?::([0-5]?\d))?$/.exec(text);
    if (clock) return parsedResult(Number(clock[1]) + Number(clock[2]) / 60 + Number(clock[3] || 0) / 3600, 'clock');

    var units = /^(?:(\d+(?:[.,]\d+)?)\s*(?:год(?:ина|ини|ин)?|г|h))?\s*(?:(\d+(?:[.,]\d+)?)\s*(?:хв(?:илин(?:а|и)?)?|m))?$/.exec(text);
    if (units && (units[1] || units[2])) {
      var hours = Number(String(units[1] || '0').replace(',', '.'));
      var minutes = Number(String(units[2] || '0').replace(',', '.'));
      if (minutes >= 60) return invalidResult();
      return parsedResult(hours + minutes / 60, 'words');
    }

    if (/^\d*(?:[.,]\d+)?$/.test(text) && /\d/.test(text)) {
      return parsedResult(Number(text.replace(',', '.')), 'decimal');
    }
    return invalidResult();
  }

  function decimal(hours) {
    if (!Number.isFinite(Number(hours))) return '—';
    return String(normaliseHours(hours)).replace('.', ',') + ' год';
  }

  function human(hours) {
    if (!Number.isFinite(Number(hours))) return '—';
    var totalMinutes = Math.round(Number(hours) * 60);
    return Math.floor(totalMinutes / 60) + ' год ' + (totalMinutes % 60) + ' хв';
  }

  function display(hours) {
    if (!Number.isFinite(Number(hours))) return '—';
    return decimal(hours) + ' (' + human(hours) + ')';
  }

  function warning(hours) {
    var value = Number(hours);
    if (!Number.isFinite(value)) return '';
    if (value < MIN_HOURS || value > MAX_HOURS) {
      return '⚠ Незвичний час: перевірте значення (очікуваний діапазон ' + MIN_HOURS + '–' + MAX_HOURS + ' год).';
    }
    return '';
  }

  root.BoosterPrintTime = Object.freeze({
    minHours: MIN_HOURS,
    maxHours: MAX_HOURS,
    parse: parse,
    decimal: decimal,
    human: human,
    display: display,
    warning: warning,
  });
}(typeof globalThis === 'object' ? globalThis : this));
