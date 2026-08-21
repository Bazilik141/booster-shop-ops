import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const code = fs.readFileSync(path.resolve(here, '../Code.gs'), 'utf8');
const properties = {};
const sent = [];
const drafts = [];
const menus = [];
let now = 1_786_000_000_000;

class FakeDate extends Date {
  static now() { return now; }
}

const scriptProperties = {
  getProperty(key) { return properties[key] || ''; },
  setProperty(key, value) { properties[key] = value; },
  deleteProperty(key) { delete properties[key]; },
};

const context = vm.createContext({
  JSON, String, Number, Boolean, Array, Object, RegExp, Math, Date: FakeDate, Error, isFinite, console,
  Logger: { log() {} },
  PropertiesService: { getScriptProperties: () => scriptProperties },
  CacheService: { getScriptCache: () => ({ get() { throw new Error('news input state must not read CacheService'); }, put() { throw new Error('news input state must not write CacheService'); }, remove() { throw new Error('news input state must not clear CacheService'); } }) },
  SpreadsheetApp: { openById() { return null; }, getActive() { return null; }, flush() {} },
  ContentService: { MimeType: { JSON: 'JSON' }, createTextOutput() { return { setMimeType() { return this; } }; } },
  HtmlService: { createHtmlOutput() { return {}; } },
  __sent: sent,
  __drafts: drafts,
  __menus: menus,
});

vm.runInContext(`${code}
tgIsAllowedChat_ = function(chatId) { return chatId === '42'; };
tgSendMessage_ = function(chatId, text) { globalThis.__sent.push({ chatId: String(chatId), text: String(text) }); };
tgShowMainMenu_ = function(chatId) { globalThis.__menus.push(String(chatId)); };
openaiDraftPostFromText_ = function(sourceLabel, text, sourceUrl) { globalThis.__drafts.push({ sourceLabel: String(sourceLabel), text: String(text), sourceUrl: String(sourceUrl) }); return { tag: 'Тест', text: 'Чернетка' }; };
globalThis.__test = { handleTelegramUpdate_, tgSetNewsInputWait_, tgGetNewsInputWait_, tgClearNewsInputWait_, tgNewsInputWaitKey_ };`, context, { filename: 'Code.gs' });

const chatId = '42';
const key = context.__test.tgNewsInputWaitKey_(chatId);
const waiting = { kind: 'post', sourceLabel: 'Стаття за посиланням', sourceUrl: 'https://example.test/article' };
const article = 'Це повний текст новини, який точно довший за сто вісімдесят символів. Він потрібен лише для регресійного тесту маршруту Telegram і не має потрапляти до тимчасового службового стану між повідомленнями.';

context.__test.tgSetNewsInputWait_(chatId, waiting);
const stored = JSON.parse(properties[key]);
assert.equal(stored.expires_at, now + 600_000, 'wait state expires in ten minutes');
assert.deepEqual(JSON.parse(JSON.stringify(stored.value)), waiting, 'only small routing context is persisted');
assert.deepEqual(JSON.parse(JSON.stringify(context.__test.tgGetNewsInputWait_(chatId))), waiting, 'the persisted state survives a separate webhook execution');

context.__test.handleTelegramUpdate_({ message: { chat: { id: chatId }, text: article } });
assert.deepEqual(JSON.parse(JSON.stringify(drafts)), [{ sourceLabel: waiting.sourceLabel, text: article, sourceUrl: waiting.sourceUrl }], 'a pasted article reaches OpenAI without CacheService');
assert.equal(properties[key], undefined, 'the state is consumed after the draft attempt');
assert.deepEqual(menus, [], 'a valid pasted article never falls through to the main menu');

context.__test.tgSetNewsInputWait_(chatId, waiting);
context.__test.handleTelegramUpdate_({ message: { chat: { id: chatId }, text: '/cancel' } });
assert.equal(properties[key], undefined, 'cancel clears the persisted wait state');
assert.match(sent.at(-1).text, /Очікування скасовано/, 'cancel confirms the reset');

context.__test.tgSetNewsInputWait_(chatId, waiting);
now += 600_001;
context.__test.handleTelegramUpdate_({ message: { chat: { id: chatId }, text: article } });
assert.equal(properties[key], undefined, 'expired state is removed');
assert.equal(drafts.length, 1, 'an expired state does not generate a draft');
assert.match(sent.at(-1).text, /Час очікування минув/, 'expiry is explicit instead of showing the menu');
assert.deepEqual(menus, [], 'expiry does not silently fall through to the main menu');

console.log('Telegram news input state tests passed');
