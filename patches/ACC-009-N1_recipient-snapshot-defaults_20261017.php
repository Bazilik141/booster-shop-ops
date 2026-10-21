<?php
/** ACC-009 guarded single-file source patch, PHP 8.0.
 * Default/--dry-run: current source/metadata checks and native lint, no source/DB writes.
 * --apply: OWNER ONLY, after fresh web-runtime gate; backup, atomic replace, lint, restore-on-fail.
 * --rollback=NAME: OWNER ONLY; exact known current bytes + private verified backup, reverse N3 before N1.
 * No runner SQL mutation. File rollback cannot undo remote NP objects created by later operator actions.
 * Web branch exposes only runtime version/OPcache flags for fresh owner admission; no config/bootstrap/NP.
 */
const ACC009S_SPEC_B64 = 'eyJpZCI6IkFDQy0wMDktTjEiLCJ0YXJnZXQiOiJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL2FkbWluL2NvbnRyb2xsZXIvc2hpcHBpbmcvaW50ZXJuZXRfZG9jdW1lbnQucGhwIiwibWFya2VyIjoiQUNDLTAwOS1OMSIsImJlZm9yZV9zaGEyNTYiOiI4ZTA3OTQ1ZGFjYzViM2ZhYjI2ODEyNjZhYzRkMDM4MTFlOWI3MDJkMjJiMDY3MDA4ZDAyYzFkNzFjNTlkNTY3IiwiYWZ0ZXJfc2hhMjU2IjoiNDNkYzQ2NmY1MzMyZDQ1YWNlY2M1ZTFjYjJlODJlOGY3MTIyNjhhZjU2OGQ2ZjE2ZGU5MDgwOWEzZGQ2MGRhMSIsImFscmVhZHlfaGFzaGVzIjpbIjQzZGM0NjZmNTMzMmQ0NWFjZWNjNWUxY2IyZTgyZThmNzEyMjY4YWY1NjhkNmYxNmRlOTA4MDlhM2RkNjBkYTEiLCIyMTM4ZTE1NjQzY2U0YmNlZDFiNDk2OTg5NTVjNzhmNDQzNjE3ZTRiNTk4NzczMjUxMTMyNjEwNDJkMWM3YjkzIl0sImVkaXRzIjpbeyJvbGQiOiIgICAgICAgICRleHBsb2RlZF9zaGlwcGluZ19maXJzdG5hbWUgPSBleHBsb2RlKFwiIFwiLCB0cmltKCRvcmRlclsnc2hpcHBpbmdfZmlyc3RuYW1lJ10pKTtcblxuICAgICAgICBpZiAoY291bnQoJGV4cGxvZGVkX3NoaXBwaW5nX2ZpcnN0bmFtZSkgPiAxKSB7XG4gICAgICAgICAgICAkcmVjaXBpZW50X21pZGRsZW5hbWUgPSBhcnJheV9wb3AoJGV4cGxvZGVkX3NoaXBwaW5nX2ZpcnN0bmFtZSk7XG4gICAgICAgICAgICAkcmVjaXBpZW50X2ZpcnN0bmFtZSA9IGltcGxvZGUoXCIgXCIsICRleHBsb2RlZF9zaGlwcGluZ19maXJzdG5hbWUpO1xuICAgICAgICB9IGVsc2Uge1xuICAgICAgICAgICAgJHJlY2lwaWVudF9taWRkbGVuYW1lID0gXCJcIjtcbiAgICAgICAgICAgICRyZWNpcGllbnRfZmlyc3RuYW1lID0gJGV4cGxvZGVkX3NoaXBwaW5nX2ZpcnN0bmFtZVswXTtcbiAgICAgICAgfSIsIm5ldyI6IiAgICAgICAgJHJlY2lwaWVudF9uYW1lcyA9ICR0aGlzLT5hY2MwMDlSZWNpcGllbnROYW1lRGVmYXVsdHMoJG9yZGVyKTtcbiAgICAgICAgJHJlY2lwaWVudF9maXJzdG5hbWUgPSAkcmVjaXBpZW50X25hbWVzWydmaXJzdG5hbWUnXTtcbiAgICAgICAgJHJlY2lwaWVudF9taWRkbGVuYW1lID0gJHJlY2lwaWVudF9uYW1lc1snbWlkZGxlbmFtZSddOyJ9LHsib2xkIjoiICAgIHB1YmxpYyBmdW5jdGlvbiBtYWtlRGVmYXVsdEZvcm1EYXRhKCRvcmRlciwgJHNldHRpbmdzKSIsIm5ldyI6IiAgICAvLyBBQ0MtMDA5LU4xOiBzaGlwcGluZyBzbmFwc2hvdCBvbmx5OyBhYnNlbnQga2V5IG1lYW5zIG1hbnVhbCBwYXRyb255bWljLlxuICAgIHByaXZhdGUgZnVuY3Rpb24gYWNjMDA5UmVjaXBpZW50TmFtZURlZmF1bHRzKGFycmF5ICRvcmRlcik6IGFycmF5XG4gICAge1xuICAgICAgICAkZmlyc3RuYW1lID0gaXNfc3RyaW5nKCRvcmRlclsnc2hpcHBpbmdfZmlyc3RuYW1lJ10gPz8gbnVsbCkgPyB0cmltKCRvcmRlclsnc2hpcHBpbmdfZmlyc3RuYW1lJ10pIDogJyc7XG4gICAgICAgICRtaWRkbGVuYW1lID0gJyc7XG4gICAgICAgICRzbmFwc2hvdCA9IGFycmF5X2tleV9leGlzdHMoJ3NoaXBwaW5nX2N1c3RvbV9maWVsZCcsICRvcmRlcikgPyAkb3JkZXJbJ3NoaXBwaW5nX2N1c3RvbV9maWVsZCddIDogW107XG5cbiAgICAgICAgaWYgKCFpc19hcnJheSgkc25hcHNob3QpKSB7XG4gICAgICAgICAgICAkdGhpcy0+ZXJyb3JzW10gPSAn0J3QtSDQstC00LDQu9C+0YHRjyDQv9GA0L7Rh9C40YLQsNGC0Lgg0LfQsdC10YDQtdC20LXQvdC1INC/0L4g0LHQsNGC0YzQutC+0LLRli4g0J/QtdGA0LXQstGW0YDRgtC1INC00LDQvdGWINC+0YLRgNC40LzRg9Cy0LDRh9CwINGC0LAg0LLQstC10LTRltGC0Ywg0ZfRhSDQstGA0YPRh9C90YMuJztcbiAgICAgICAgfSBlbHNlaWYgKGFycmF5X2tleV9leGlzdHMoJ3JkMTNfcmVjZWl2ZXJfbWlkZGxlbmFtZScsICRzbmFwc2hvdCkpIHtcbiAgICAgICAgICAgICR2YWx1ZSA9ICRzbmFwc2hvdFsncmQxM19yZWNlaXZlcl9taWRkbGVuYW1lJ107XG4gICAgICAgICAgICBpZiAoaXNfc3RyaW5nKCR2YWx1ZSkgJiYgc3RycG9zKCR2YWx1ZSwgXCJcXDBcIikgPT09IGZhbHNlICYmIHByZWdfbWF0Y2goJy8vdScsICR2YWx1ZSkgPT09IDEpIHtcbiAgICAgICAgICAgICAgICAkbWlkZGxlbmFtZSA9ICR2YWx1ZTtcbiAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgJHRoaXMtPmVycm9yc1tdID0gJ9Cd0LUg0LLQtNCw0LvQvtGB0Y8g0L/RgNC+0YfQuNGC0LDRgtC4INC30LHQtdGA0LXQttC10L3QtSDQv9C+INCx0LDRgtGM0LrQvtCy0ZYuINCf0LXRgNC10LLRltGA0YLQtSDQtNCw0L3RliDQvtGC0YDQuNC80YPQstCw0YfQsCDRgtCwINCy0LLQtdC00ZbRgtGMINGX0YUg0LLRgNGD0YfQvdGDLic7XG4gICAgICAgICAgICB9XG4gICAgICAgIH1cblxuICAgICAgICByZXR1cm4gWydmaXJzdG5hbWUnID0+ICRmaXJzdG5hbWUsICdtaWRkbGVuYW1lJyA9PiAkbWlkZGxlbmFtZV07XG4gICAgfVxuXG4gICAgLy8gT25seSB2aWV3IGNvcGllcyBhcmUgZW5jb2RlZCwgYWZ0ZXIgdmFsaWRhdGlvbiBhbmQgb3V0Ym91bmQgQVBJIHByb2Nlc3NpbmcuXG4gICAgcHJpdmF0ZSBmdW5jdGlvbiBhY2MwMDlSZWNpcGllbnREaXNwbGF5RGF0YShhcnJheSAkZm9ybWRhdGEpOiBhcnJheVxuICAgIHtcbiAgICAgICAgZm9yZWFjaCAoWydyZWNpcGllbnRfZmlyc3RuYW1lJywgJ3JlY2lwaWVudF9sYXN0bmFtZScsICdyZWNpcGllbnRfbWlkZGxlbmFtZSddIGFzICRmaWVsZCkge1xuICAgICAgICAgICAgJHZhbHVlID0gJGZvcm1kYXRhWyRmaWVsZF0gPz8gJyc7XG4gICAgICAgICAgICBpZiAoIWlzX3N0cmluZygkdmFsdWUpIHx8IHN0cnBvcygkdmFsdWUsIFwiXFwwXCIpICE9PSBmYWxzZSB8fCBwcmVnX21hdGNoKCcvL3UnLCAkdmFsdWUpICE9PSAxKSB7XG4gICAgICAgICAgICAgICAgJGZvcm1kYXRhWyRmaWVsZF0gPSAnJztcbiAgICAgICAgICAgICAgICAkdGhpcy0+ZXJyb3JzW10gPSAn0J/QtdGA0LXQstGW0YDRgtC1INGC0LAg0LLQstC10LTRltGC0Ywg0LTQsNC90ZYg0L7RgtGA0LjQvNGD0LLQsNGH0LAg0LLRgNGD0YfQvdGDOiDQt9Cx0LXRgNC10LbQtdC90LUg0LfQvdCw0YfQtdC90L3RjyDQvdC10LzQvtC20LvQuNCy0L4g0LLRltC00L7QsdGA0LDQt9C40YLQuC4nO1xuICAgICAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgICAgICAkZm9ybWRhdGFbJGZpZWxkXSA9IGh0bWxzcGVjaWFsY2hhcnMoJHZhbHVlLCBFTlRfUVVPVEVTLCAnVVRGLTgnLCBmYWxzZSk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH1cblxuICAgICAgICByZXR1cm4gJGZvcm1kYXRhO1xuICAgIH1cblxuXG4gICAgcHVibGljIGZ1bmN0aW9uIG1ha2VEZWZhdWx0Rm9ybURhdGEoJG9yZGVyLCAkc2V0dGluZ3MpIn0seyJvbGQiOiIgICAgICAgICRkYXRhWydlcnJvcnMnXSA9ICR0aGlzLT5lcnJvcnM7IiwibmV3IjoiICAgICAgICAvLyBBQ0MtMDA5LU4xOiBlbmNvZGUgcmVjaXBpZW50IHZpZXcgY29waWVzIGFmdGVyIG91dGJvdW5kIHByb2Nlc3NpbmcuXG4gICAgICAgICRkYXRhWydmb3JtZGF0YSddID0gJHRoaXMtPmFjYzAwOVJlY2lwaWVudERpc3BsYXlEYXRhKCRkYXRhWydmb3JtZGF0YSddKTtcblxuICAgICAgICAkZGF0YVsnZXJyb3JzJ10gPSAkdGhpcy0+ZXJyb3JzOyJ9XSwicnVudGltZSI6eyJleHBlY3RlZCI6eyJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9ldmVudC9sYW5ndWFnZS5waHAiOiJlYmMwMTFiZTIxYWE3OGY3NjcxZGQ1NDQ4NGU3ZGZkZWZkMTQ0MWM0YjAzNDI0ZDNhM2Y3YzQ1YjA1YzgyNDNmIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvZXZlbnQvbW9kaWZpY2F0aW9uLnBocCI6IjE3YWJhNzBkNTU3ZDVhODYzZWY2NTUwZmRmOTczZWQxYjJkZDZiMGIwZjg0YmE2NzYxZjcwMTc4M2I2NTM1ZDAiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9zdGFydHVwL2FwcGxpY2F0aW9uLnBocCI6ImRjODMzMjAyMjdlYmU5YzdjODU2ZDJiZTBkNjMxYjYwYjhmN2RkN2FmNGM1OWI2M2Q2M2VlMDQ1NTI1YTU3NjkiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9zdGFydHVwL2F1dGhvcml6ZS5waHAiOiJkN2MyNTY2NDIyYWJlZTNlYjIwZjc3ZTE3YzU4ODgzYjIzODQyNDRlZWNlOThhMjA0NzJiMGJkYTdhY2UxYTAwIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9lcnJvci5waHAiOiI3ZDcxNGI5YjAzNDYwOWVhOGRmYjk1MWU3ODk3YmI4MzZmMTg3Mjk0ZTA0NzZhOGE1YTM4MTM0MGM1YjM0NjhjIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9ldmVudC5waHAiOiJiOTA2ZmZlMzcyMmY1ODQ2Nzk4OGRmYTc2OGE2ZWEzZWNjNTc4M2I3YmExZmM4ZWUzMThiYjdjNTM1YWIxODAwIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9leHRlbnNpb24ucGhwIjoiNDUxNDY4ZDUxNTdhN2M5NzI5YzYxM2ZkYWMxYTBkNjE4MzNjNmUyMDBjNTNlNGE4YWRmMmJjZDJhOTQxYjg1MSIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL3N0YXJ0dXAvbGFuZ3VhZ2UucGhwIjoiYTc1ODhmYjcwNTUzZmYzMTFhY2IyY2UyNDM1MmY2YmU5ODMwNDRlNzRlZjc0OGQ3NjA0YzAwMGZlNTU5MDY0MyIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL3N0YXJ0dXAvbG9naW4ucGhwIjoiYTYzYzZhYjhjOWRjYzM0ZTNlMDNhNjJlZmU5YjhmNGRhODllZTAxM2ZhZmZlZTg5YTNhYTM4NWIzNzJmNjU4MCIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL3N0YXJ0dXAvcGVybWlzc2lvbi5waHAiOiJiYzhlMjFmNzczNDE0OTc5MzIwNzY2YWYwNDI2M2EzNDAwOWUwNWQzZjcwMmRmNjU4ZjIwY2RjOTMyNzNkMTY5IiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9zYXNzLnBocCI6IjU1MDE3NzcxZmFiOTUyNWZiNzNjODE0Yzc4ZjgyN2JlNzJkZjhmZmY5MjM5Y2IxOTU2NmM5NTkzZmIwMTRiMGMiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9zdGFydHVwL3Nlc3Npb24ucGhwIjoiZDkxMzVmMzdlMzYxZjkxZjNiMTgyZWZjZmY1NDkwMGJmMjRhYzgyNDYzM2QxODJhZDkzZmVmNDk1ZWNmOWJhMiIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL3N0YXJ0dXAvc2V0dGluZy5waHAiOiJhMTBmZjZiODU4NzMyNDhlNGFlNzJkNzk5ZjYyYjE4NmIxZTNjNWVhM2EzMDQzZTc3NDRkOTZjZmEyYmIyMWJkIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9zdGFydHVwLnBocCI6IjBkZjJhZWRlNGYyMDU5ZmQ5YjdlNGI1ZTZhNmUxMjU4NmFjNjFmZGMyMGJhMmM3NTAzMTI0ZmM1MGU1ZDBkZDIiLCJhZG1pbkV2aGVuaWkvaW5kZXgucGhwIjoiMTRmNDQyYmJmMTg3ZjUyYjlkOTk3ZmY4M2IzMzU4NjEyZmJlODVlMDMwZjQ5NGNhMGUwODRiNTIwYmU4NWFkMyIsImFkbWluRXZoZW5paS9tb2RlbC9zYWxlL29yZGVyLnBocCI6ImU5MDA1ZWI0NTk3ZjY5MDNiZTEzM2Y0ZDAwNzdkMGQ0ZDY4YTFhNjM3MmY2ODM1N2NlNWMyNWNlOGQ4NTdiNWQiLCJhZG1pbkV2aGVuaWkvbW9kZWwvc2V0dGluZy9ldmVudC5waHAiOiIzNzg2MWZkYWVmYTg0YzgxNmViZjMxM2MzN2ZmYjMzM2UyMzNlYjcxYTVmYWE5ODk4MGM4Y2M3OTE5NWVjYjI0IiwiYWRtaW5FdmhlbmlpL21vZGVsL3NldHRpbmcvc3RhcnR1cC5waHAiOiI4OTlmMDU1ZTFiNDM0MjRjYjAzYmM1NGY4NDJhMDA2NDlmOTU0ZTkzZDBlMDA2MjdhMmVmMWIwYWVlNWRjMDczIiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9hZG1pbi9jb250cm9sbGVyL3BheW1lbnQvcGludGFfbm92YV9wb3NodGFfY29kLnBocCI6IjMzZDU1NDEwOGZiMTBmNjQ4MjQ2OWJhNTRhMTJlNmYxNzg0NzU3ZDFjZmE0MTEwM2IzYTRhZDQ5OGIzYjc3YmMiLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL2FkbWluL2NvbnRyb2xsZXIvc2hpcHBpbmcvaW50ZXJuZXRfZG9jdW1lbnQucGhwIjoiOGUwNzk0NWRhY2M1YjNmYWIyNjgxMjY2YWM0ZDAzODExZTliNzAyZDIyYjA2NzAwOGQwMmMxZDcxYzU5ZDU2NyIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2QvYWRtaW4vY29udHJvbGxlci9zaGlwcGluZy9waW50YV9ub3ZhX3Bvc2h0YS5waHAiOiJlOWUzMDA2Yjc5ZDQwZjY1ZGQzODE3NWM4MDlmOGFiZmI1MGQwMDNkYWYxMTMwNzBlNDZkN2JmNjFmMjAzZGQzIiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9hZG1pbi9jb250cm9sbGVyL3NoaXBwaW5nL3BpbnRhX25vdmFfcG9zaHRhL3BpbnRhX3ZhbGlkYXRlX2FwaV9yZXN1bHRfdHJhaXQucGhwIjoiMDNlYjMwZDBjM2YyNGExNzkxMmZkZmNjZGM3ZjMyMDM1N2MxMDc0MTdiYTQ1MGY1ZDkzYmRhN2JlMDFhNmViMyIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2QvYWRtaW4vbW9kZWwvbW9kdWxlL2NpdHkucGhwIjoiNzU0NTBhYWRkODA3MjhhMDc5ZTE0YTY5YWNkNGVmOTg1OTk5NTZmN2Q5YTczZTQ0YWQ0NjBjNGZmNDJlMjRkNiIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2QvYWRtaW4vbW9kZWwvbW9kdWxlL2NvdW50ZXJwYXJ0eS5waHAiOiI0YWMxNmI0NzA0YTk4NDYzMTQ5ZjA4NzgwNTA0ZDIxOGFhYWY2YjRjODJlMTRhY2EyNmIwYjMyMDQ2MDIwYWU2IiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9hZG1pbi9tb2RlbC9tb2R1bGUvaW50ZXJuZXRfZG9jdW1lbnQucGhwIjoiZmUxOTlkZGIyMjZmMGQ5YTc1NTU5ZTVkY2ZmOGNjNTJiZTkzMzAwNTI3ZjFkYzY1NjhiN2E5YTVhODBkMTE5NyIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2QvYWRtaW4vbW9kZWwvbW9kdWxlL3N0cmVldC5waHAiOiI4YjA0NjY5OWU2MzAwYmYxMGUzNWYxMTcyOTIyNjA0NGU5ZTczNWQ4ZmY2OTc1YTFmNTJiMGVlNGMxMWMzYTVhIiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9hZG1pbi92aWV3L2phdmFzY3JpcHQvcGludGFfbm92YV9wb3NodGEvY29tbW9uLmpzIjoiOWFlNWFiZDBhNGExZWI3OTdmZWUwMzcyM2RkNjg4ZmI3NzJmMTVmNWRjZGMxNzU2YTNlOGQ5NjZkMGJmZWE2OSIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2QvYWRtaW4vdmlldy90ZW1wbGF0ZS9zaGlwcGluZy9waW50YV9ub3ZhX3Bvc2h0YS9jcmVhdGVfaW50ZXJuZXRfZG9jdW1lbnQudHdpZyI6IjY4NDI0NzA0ZjUyOThhOWYwYzgwZWRmMzU3MjdkZWNjMzdhNjlmZjcxOTEzNjA0OTg0MWExZWYzYTQwMmJhYzUiLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL3N5c3RlbS9saWJyYXJ5L3BpbnRhbm92YXBvc2h0YS9waW50YWNvdW50ZXJwYXJ0eS5waHAiOiI0NGQxMzhlZGNhMzViODNlYjdlMTI0MTYxY2Y4ZmMwZmMxOWQwODRkYjhhNjM1ZjMyYjkwMWNjMDBiNzczYmI3IiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9zeXN0ZW0vbGlicmFyeS9waW50YW5vdmFwb3NodGEvcGludGFpbnRlcm5ldGRvY3VtZW50LnBocCI6IjBhOTIxNmU1YWI0OGRhYWM4ZmFhNTg1MWFkMzljYzBkZGVmOTUxOWU2ZTNmYmFhZTRmNzdkMjIxZDEzYjAwY2MiLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL3N5c3RlbS9saWJyYXJ5L3BpbnRhbm92YXBvc2h0YS9waW50YW5vdmFwb3NodGFhcGkucGhwIjoiNzI5YzNjNmNlZTZiMDRmOTI1OTY3YTllNDIyNzdiOTcwMmU1MTg3NmIzN2Q2ZGYxMTliYTQwMjU4YjIwYjE5YyIsInN5c3RlbS9jb25maWcvYWRtaW4ucGhwIjoiMWE5ODZkZjhjYjVmNWJhMjJkODJhYWRiM2ZjNGQwNTk2MGFiYzIwYzZhMTA5NTQ4NmM0YTMwYzYxMzMxZjlhZiIsInN5c3RlbS9jb25maWcvZGVmYXVsdC5waHAiOiJjNTZkNDFkMDgzMzZhYWM2ZDY1MmEzYjVlY2Q4N2ExMWJlMDUzMzRhMjJiNGZmOTAxN2ZhMDNkYmVhZjcwODcwIiwic3lzdGVtL2VuZ2luZS9hY3Rpb24ucGhwIjoiOTFhNGFmMzc3NWU5YzFjNTk5ZmFmNTVmMmIxZjllYWM4OWI4OWQxNzg2NTdjNmJkOGVjN2I2MTA1NDM3M2U3OSIsInN5c3RlbS9lbmdpbmUvYXV0b2xvYWRlci5waHAiOiJjMzEwZjFjYWM1MzE4MGIzMDFkZDA0OGYwMTc1YTM1OWJlZTFjY2U3MGY0Yzk0Mjg1MWI4YWQzMDUzOWY5OWIzIiwic3lzdGVtL2VuZ2luZS9jb25maWcucGhwIjoiZDFhNzI0MDdjNzdmNmRkYzQ0OGNjYWU5MGQ0ODljZDU5MDdlNWNiNDEwOGU3ZGVjY2E0YjQ2ZWIzNTQxYmQwZCIsInN5c3RlbS9lbmdpbmUvY29udHJvbGxlci5waHAiOiI4YTUxZWYxMTI0ZGEzYjliNmFlOTFiMmVjOTE0ZGYxZjJiYmU5NTE0MmI3NTM0MzFmN2NmYTBlNzUwY2JlMDNiIiwic3lzdGVtL2VuZ2luZS9ldmVudC5waHAiOiI3YzEzMmE0ZGQxNzQ5MjE0NjFiNDc3NDY0N2FlZTdmNGIzOTc0YzUzZmFiZTg0ZTZhMGNjZDc0YjBkNzM3NTI0Iiwic3lzdGVtL2VuZ2luZS9mYWN0b3J5LnBocCI6ImZkOGE4OTQwOTE1ZDlhZTNkYjJjMGJjZDVjZWJlMjI1NDRmNjUzMjU3MjY0NzgwMDY2ZTNhNWEyYWU3Y2Y3ZmUiLCJzeXN0ZW0vZW5naW5lL2xvYWRlci5waHAiOiJlYTIwOTg0NTBkZGM1Njc3NDdlMmQ4OTAwMWQ5OTdjZTkyMWU5NjZhMzEzY2FmYjY4MGEzMDhlZTM2OWE0NzIzIiwic3lzdGVtL2VuZ2luZS9tb2RlbC5waHAiOiJjZDNlMDQyNmJmODBjMDBlNjU0YmE1MThmYTRjMGIzZTg0Njg2NjZjMzlmODYwMTFlMTEwMjllY2JlMjhkOTUwIiwic3lzdGVtL2VuZ2luZS9wcm94eS5waHAiOiI1NjlhZjhiNTA5MDliZTI1ZGNkMzdmNmQ4YWZjMzQ3OGFkYTA4YWYzZDU5ZDNlYzVkMTY2MTg3NjYyY2E0NGQ5Iiwic3lzdGVtL2VuZ2luZS9yZWdpc3RyeS5waHAiOiIzMjVjNmUwZjY3ZmRmZGEyMDkyNjcwNDg1YjNhYzM4ZmNjOTExNjc4OTBjMjUzMTYwOWFjYWViODczOTMzNjM3Iiwic3lzdGVtL2ZyYW1ld29yay5waHAiOiI3YmQyMWY5YjJkOTE2N2IzZjcwYzE4OTk2ODdjMTg3ZDc2OTAyMjQ0M2QyMDkxYTNkZTkzMGE0MWMxNGI1MDg4Iiwic3lzdGVtL2xpYnJhcnkvcmVxdWVzdC5waHAiOiI0ODNjMWE0NGVjMGM3YzlkNjk2OTU3MGQ3NjZjMWNkOGM0MGU3NjQ2ZDE5ZGEwZjdhMTFkMGRkYTc5NzlmNTJjIiwic3lzdGVtL2xpYnJhcnkvdGVtcGxhdGUucGhwIjoiN2ZkZTZiOTU2OWFhODc5NGYxNGRlZThjOGMwNjc3NTkxNDUzMzQyZGVmZTA4ODJjMjA4ZDRiNjg5NmIzMDZkMCIsInN5c3RlbS9saWJyYXJ5L3RlbXBsYXRlL3R3aWcucGhwIjoiOTJhNmFjMGFmNmZmODgwNTNlY2FmZTBhY2Q4NmUyNDliZGRhYWI2NjQwODBhNTQ5YzBhNTAyMTc5ZjUzN2E2OSIsImFkbWluRXZoZW5paS9tb2RlbC9zZXR0aW5nL2V4dGVuc2lvbi5waHAiOiI2N2VkYjdlYjczOTlmMzVkZTUxMWRiMzIzOTgxNWU0M2M0YmYxMjcxNzcxZDc5YmVkNjNjZjlmMGU1OGYyZGZiIiwiYWRtaW5FdmhlbmlpL21vZGVsL3NldHRpbmcvc2V0dGluZy5waHAiOiI0MjMxNWI2ODVkODlhMjZmZmNjOTg5YzVkZTk5OWFlZDczNjA0NGIzNTYwZDkzNDRkZDRkOGQ1MjhlYTVhMWQ3In0sImFkbWluX2V2ZW50cyI6W3siY29kZSI6ImFuYWx5dGljc19wc19lbmhhbmNlZF9tZWFzdXJlbWVudCIsInRyaWdnZXIiOiJhZG1pbi9jb250cm9sbGVyL3NhbGUvb3JkZXIuY2FsbC9hZnRlciIsImFjdGlvbiI6ImV4dGVuc2lvbi9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC9hbmFseXRpY3MvcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQuZXZlbnRBZG1pbkNvbnRyb2xsZXJTYWxlT3JkZXJBZGRIaXN0b3J5QWZ0ZXIiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MH0seyJjb2RlIjoiYW5hbHl0aWNzX3BzX2VuaGFuY2VkX21lYXN1cmVtZW50IiwidHJpZ2dlciI6ImFkbWluL3ZpZXcvc2FsZS9vcmRlcl9pbmZvL2JlZm9yZSIsImFjdGlvbiI6ImV4dGVuc2lvbi9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC9hbmFseXRpY3MvcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQuZXZlbnRBZG1pblZpZXdTYWxlT3JkZXJJbmZvQmVmb3JlIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjB9LHsiY29kZSI6ImNhcHRjaGFfcHNfZ29vZ2xlX3JlY2FwdGNoYSIsInRyaWdnZXIiOiJhZG1pbi9jb250cm9sbGVyL2NvbW1vbi9sb2dpbi5sb2dpbi9hZnRlciIsImFjdGlvbiI6ImV4dGVuc2lvbi9wc19nb29nbGVfcmVjYXB0Y2hhL2NhcHRjaGEvcHNfZ29vZ2xlX3JlY2FwdGNoYS5ldmVudEFkbWluQ29udHJvbGxlckNvbW1vbkxvZ2luTG9naW5BZnRlciIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjowfSx7ImNvZGUiOiJjYXB0Y2hhX3BzX2dvb2dsZV9yZWNhcHRjaGEiLCJ0cmlnZ2VyIjoiYWRtaW4vdmlldy9jb21tb24vaGVhZGVyL2JlZm9yZSIsImFjdGlvbiI6ImV4dGVuc2lvbi9wc19nb29nbGVfcmVjYXB0Y2hhL2NhcHRjaGEvcHNfZ29vZ2xlX3JlY2FwdGNoYS5ldmVudEFkbWluVmlld0NvbW1vbkhlYWRlckJlZm9yZSIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjowfSx7ImNvZGUiOiJjYXB0Y2hhX3BzX2dvb2dsZV9yZWNhcHRjaGEiLCJ0cmlnZ2VyIjoiYWRtaW4vdmlldy9jb21tb24vbG9naW4vYmVmb3JlIiwiYWN0aW9uIjoiZXh0ZW5zaW9uL3BzX2dvb2dsZV9yZWNhcHRjaGEvY2FwdGNoYS9wc19nb29nbGVfcmVjYXB0Y2hhLmV2ZW50QWRtaW5WaWV3Q29tbW9uTG9naW5CZWZvcmUiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MH0seyJjb2RlIjoiY2FwdGNoYV9wc19nb29nbGVfcmVjYXB0Y2hhIiwidHJpZ2dlciI6ImFkbWluL3ZpZXcvc2V0dGluZy9zZXR0aW5nL2JlZm9yZSIsImFjdGlvbiI6ImV4dGVuc2lvbi9wc19nb29nbGVfcmVjYXB0Y2hhL2NhcHRjaGEvcHNfZ29vZ2xlX3JlY2FwdGNoYS5ldmVudEFkbWluVmlld1NldHRpbmdTZXR0aW5nQmVmb3JlIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjB9LHsiY29kZSI6Im1vZHVsZV9wc19wcm9kdWN0X2NhdGVnb3J5X2ZpbHRlciIsInRyaWdnZXIiOiJhZG1pbi9tb2RlbC9jYXRhbG9nL3Byb2R1Y3QuZWRpdFByb2R1Y3QvYWZ0ZXIiLCJhY3Rpb24iOiJleHRlbnNpb24vcHNfcHJvZHVjdF9jYXRlZ29yeV9maWx0ZXIvbW9kdWxlL3BzX3Byb2R1Y3RfY2F0ZWdvcnlfZmlsdGVyLmV2ZW50QWRtaW5Nb2RlbENhdGFsb2dQcm9kdWN0RWRpdFByb2R1Y3RBZnRlciIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjowfSx7ImNvZGUiOiJtb2R1bGVfcHNfcHJvZHVjdF9jYXRlZ29yeV9maWx0ZXIiLCJ0cmlnZ2VyIjoiYWRtaW4vdmlldy9jYXRhbG9nL2NhdGVnb3J5X2Zvcm0vYmVmb3JlIiwiYWN0aW9uIjoiZXh0ZW5zaW9uL3BzX3Byb2R1Y3RfY2F0ZWdvcnlfZmlsdGVyL21vZHVsZS9wc19wcm9kdWN0X2NhdGVnb3J5X2ZpbHRlci5ldmVudEFkbWluVmlld0NhdGFsb2dDYXRlZ29yeUZvcm1CZWZvcmUiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MH0seyJjb2RlIjoiYWRtaW5fY3VycmVuY3lfYWRkIiwidHJpZ2dlciI6ImFkbWluL21vZGVsL2xvY2FsaXNhdGlvbi9jdXJyZW5jeS5hZGRDdXJyZW5jeS9hZnRlciIsImFjdGlvbiI6ImV2ZW50L2N1cnJlbmN5Iiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX2N1cnJlbmN5X2VkaXQiLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvbG9jYWxpc2F0aW9uL2N1cnJlbmN5LmVkaXRDdXJyZW5jeS9hZnRlciIsImFjdGlvbiI6ImV2ZW50L2N1cnJlbmN5Iiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX2N1cnJlbmN5X3NldHRpbmciLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvc2V0dGluZy9zZXR0aW5nLmVkaXRTZXR0aW5nL2FmdGVyIiwiYWN0aW9uIjoiZXZlbnQvY3VycmVuY3kiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoiYWRtaW5fbWFpbF9hZmZpbGlhdGVfYXBwcm92ZSIsInRyaWdnZXIiOiJhZG1pbi9tb2RlbC9jdXN0b21lci9jdXN0b21lcl9hcHByb3ZhbC5hcHByb3ZlQWZmaWxpYXRlL2FmdGVyIiwiYWN0aW9uIjoibWFpbC9hZmZpbGlhdGUuYXBwcm92ZSIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjoxfSx7ImNvZGUiOiJhZG1pbl9tYWlsX2FmZmlsaWF0ZV9kZW55IiwidHJpZ2dlciI6ImFkbWluL21vZGVsL2N1c3RvbWVyL2N1c3RvbWVyX2FwcHJvdmFsLmRlbnlBZmZpbGlhdGUvYWZ0ZXIiLCJhY3Rpb24iOiJtYWlsL2FmZmlsaWF0ZS5kZW55Iiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX21haWxfY3VzdG9tZXJfYXBwcm92ZSIsInRyaWdnZXIiOiJhZG1pbi9tb2RlbC9jdXN0b21lci9jdXN0b21lcl9hcHByb3ZhbC5hcHByb3ZlQ3VzdG9tZXIvYWZ0ZXIiLCJhY3Rpb24iOiJtYWlsL2N1c3RvbWVyLmFwcHJvdmUiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoiYWRtaW5fbWFpbF9jdXN0b21lcl9kZW55IiwidHJpZ2dlciI6ImFkbWluL21vZGVsL2N1c3RvbWVyL2N1c3RvbWVyX2FwcHJvdmFsLmRlbnlDdXN0b21lci9hZnRlciIsImFjdGlvbiI6Im1haWwvY3VzdG9tZXIuZGVueSIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjoxfSx7ImNvZGUiOiJhZG1pbl9tYWlsX2N1c3RvbWVyX3RyYW5zYWN0aW9uIiwidHJpZ2dlciI6ImFkbWluL21vZGVsL2N1c3RvbWVyL2N1c3RvbWVyLmFkZFRyYW5zYWN0aW9uL2FmdGVyIiwiYWN0aW9uIjoibWFpbC90cmFuc2FjdGlvbiIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjoxfSx7ImNvZGUiOiJhZG1pbl9tYWlsX2dkcHIiLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvY3VzdG9tZXIvZ2Rwci5lZGl0U3RhdHVzL2FmdGVyIiwiYWN0aW9uIjoibWFpbC9nZHByIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX21haWxfcmV0dXJuIiwidHJpZ2dlciI6ImFkbWluL21vZGVsL3NhbGUvcmV0dXJucy5hZGRIaXN0b3J5L2FmdGVyIiwiYWN0aW9uIjoibWFpbC9yZXR1cm5zIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX21haWxfcmV3YXJkIiwidHJpZ2dlciI6ImFkbWluL21vZGVsL2N1c3RvbWVyL2N1c3RvbWVyLmFkZFJld2FyZC9hZnRlciIsImFjdGlvbiI6Im1haWwvcmV3YXJkIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX21haWxfdXNlcl9hdXRob3JpemUiLCJ0cmlnZ2VyIjoiYWRtaW4vY29udHJvbGxlci9jb21tb24vYXV0aG9yaXplLnNlbmQvYWZ0ZXIiLCJhY3Rpb24iOiJtYWlsL2F1dGhvcml6ZSIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjoxfSx7ImNvZGUiOiJhZG1pbl9tYWlsX3VzZXJfYXV0aG9yaXplX3Jlc2V0IiwidHJpZ2dlciI6ImFkbWluL21vZGVsL3VzZXIvdXNlci5hZGRUb2tlbi9hZnRlciIsImFjdGlvbiI6Im1haWwvYXV0aG9yaXplLnJlc2V0Iiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX21haWxfdXNlcl9mb3Jnb3R0ZW4iLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvdXNlci91c2VyLmFkZFRva2VuL2FmdGVyIiwiYWN0aW9uIjoibWFpbC9mb3Jnb3R0ZW4iLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoic3RhdGlzdGljc19yZXR1cm5fZGVsZXRlIiwidHJpZ2dlciI6ImFkbWluL21vZGVsL3NhbGUvcmV0dXJucy5kZWxldGVSZXR1cm4vYWZ0ZXIiLCJhY3Rpb24iOiJldmVudC9zdGF0aXN0aWNzLmRlbGV0ZVJldHVybiIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjoxfSx7ImNvZGUiOiJwaW50YV9ub3ZhX3Bvc2h0YV9hZG1pbl9jb250cm9sbGVyX3NhbGVfb3JkZXJfYWZ0ZXIiLCJ0cmlnZ2VyIjoiYWRtaW4vdmlldy9zYWxlL29yZGVyX2luZm8vYWZ0ZXIiLCJhY3Rpb24iOiJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL3BheW1lbnQvcGludGFfbm92YV9wb3NodGFfY29kLmFsdGVyT3JkZXJBZGRlZEJ0biIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjo5OX1dLCJleHRlbnNpb25fY29kZXMiOlsiUGludGFOb3ZhUG9zaHRhQ29kIiwiU2ltcGxlQ2hlY2tvdXQiLCJjaWFjY291bnRzaWRlYmFyIiwiZHZfZGlhbG9naWZ5IiwiZHZfb3BlbmNhcnRfcGF0Y2giLCJkdl9zaW1wbGVfaHRtbF9kb20iLCJodXRrbyIsIm1vbm9fY2hhc3QiLCJvY19sYW5ndWFnZV9leGFtcGxlIiwib2Nfb2Ntb2RfZXhhbXBsZSIsIm9jX3BheW1lbnRfZXhhbXBsZSIsIm9jX3RoZW1lX2V4YW1wbGUiLCJvcGVuY2FydCIsInBzX2VuaGFuY2VkX21lYXN1cmVtZW50IiwicHNfZ29vZ2xlX3JlY2FwdGNoYSIsInBzX2dvb2dsZV9zaXRlbWFwIiwicHNfbGl2ZV9zZWFyY2giLCJwc19wb3B1cF9kaWFsb2dfYm94IiwicHNfcHJvZHVjdF9jYXRlZ29yeV9maWx0ZXIiLCJwdW1iX2NyZWRpdCIsInRlbGVncmFtX25vdGlmeSIsInVrcmFpbmlhbiIsInZlbnRvY2FydCJdfX0=';
class Acc009AdmissionFailure extends RuntimeException {}
function acc009a_fail(string $code): void { throw new Acc009AdmissionFailure($code); }
function acc009a_config_literals(string $code): array {
    if (!function_exists('token_get_all') || strlen($code) > 524288) acc009a_fail('config_parser_unavailable_or_size_cap');
    $tokens = array_values(array_filter(token_get_all($code), static function($t) {
        return !is_array($t) || !in_array($t[0], [T_WHITESPACE,T_COMMENT,T_DOC_COMMENT], true);
    }));
    if (!isset($tokens[0]) || !is_array($tokens[0]) || $tokens[0][0] !== T_OPEN_TAG) acc009a_fail('config_literal_grammar_required');
    $values = []; $count = count($tokens); $storedBytes=0;
    for ($i = 1; $i < $count;) {
        $t = $tokens[$i];
        if (is_array($t) && $t[0] === T_CLOSE_TAG && $i === $count - 1) break;
        if (!is_array($t) || !in_array(strtolower($t[1]), ['define','\\define'], true)
            || !in_array($t[0], [T_STRING,T_NAME_FULLY_QUALIFIED], true)
            || ($tokens[$i+1] ?? null) !== '(' || !is_array($tokens[$i+2] ?? null)
            || $tokens[$i+2][0] !== T_CONSTANT_ENCAPSED_STRING || ($tokens[$i+3] ?? null) !== ',') acc009a_fail('config_literal_grammar_required');
        $name = acc009a_config_string($tokens[$i+2][1]);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $name) || array_key_exists($name, $values)) acc009a_fail('config_duplicate_or_indirect_definition');
        $j=$i+4; $value='';
        while (true) {
            $operand=$tokens[$j]??null;
            if (!is_array($operand)) acc009a_fail('config_literal_grammar_required');
            if ($operand[0]===T_CONSTANT_ENCAPSED_STRING) $piece=acc009a_config_string($operand[1]);
            elseif ($operand[0]===T_LNUMBER && preg_match('/^(?:0|[1-9][0-9]*)$/D',$operand[1])) $piece=$operand[1];
            elseif ($operand[0]===T_STRING && array_key_exists($operand[1],$values)) $piece=$values[$operand[1]];
            else acc009a_fail('config_unknown_or_unsafe_operand');
            if (strlen($value)+strlen($piece)>524288) acc009a_fail('config_evaluated_value_size_cap');
            $value.=$piece; $j++;
            if (($tokens[$j]??null)==='.') { $j++; continue; }
            if (($tokens[$j]??null)!==')'||($tokens[$j+1]??null)!==';') acc009a_fail('config_literal_grammar_required');
            break;
        }
        $storedBytes+=strlen($value);
        if ($storedBytes>2097152) acc009a_fail('config_aggregate_value_size_cap');
        $values[$name]=$value; $i=$j+2;
    }
    return $values;
}
function acc009a_config_string(string $literal): string {
    if (strlen($literal)<2 || !in_array($literal[0],["'",'"'],true) || substr($literal,-1)!==$literal[0]) acc009a_fail('config_binary_or_invalid_string_literal');
    $value = substr($literal, 1, -1);
    if ($literal[0] === "'") return str_replace(["\\'",'\\\\'], ["'",'\\'], $value);
    // Double-quoted strings with escapes/interpolation require explicit review.
    if (strpos($value, '\\') !== false || strpos($value, '$') !== false) acc009a_fail('config_escaped_double_quote_requires_review');
    return $value;
}

function acc009a_path(string $root, string $relative): string {
    if (!preg_match('~^[A-Za-z0-9_./-]+$~D', $relative) || strpos($relative, '..') !== false || $relative[0] === '/') acc009a_fail('unsafe_relative_path');
    $path = $root;
    foreach (explode('/', $relative) as $part) {
        $path .= '/' . $part;
        if (is_link($path)) acc009a_fail('linked_source_or_config');
    }
    return $path;
}
function acc009a_config(string $root): array {
    $configs = [];
    foreach (['catalog'=>'config.php', 'admin'=>'adminEvhenii/config.php'] as $app=>$relative) {
        $path = acc009a_path($root, $relative);
        if (!is_file($path) || filesize($path)>524288) acc009a_fail('hosting_config_missing_or_size_cap');
        $body = file_get_contents($path);
        if (!is_string($body)) acc009a_fail('hosting_config_unreadable');
        $v = acc009a_config_literals($body); unset($body);
        foreach (['DB_HOSTNAME','DB_PORT','DB_DATABASE','DB_PREFIX','DB_DRIVER','DB_USERNAME','DB_PASSWORD','DIR_OPENCART','DIR_APPLICATION','DIR_SYSTEM','DIR_EXTENSION','DIR_CONFIG','DIR_TEMPLATE','APPLICATION'] as $key) {
            if (!array_key_exists($key,$v)) acc009a_fail('hosting_config_incomplete');
        }
        if ($v['APPLICATION'] !== ($app==='admin'?'Admin':'Catalog') || $v['DB_DRIVER']!=='mysqli'
            || stripos($v['DB_HOSTNAME'],'p:')===0 || !preg_match('/^[1-9][0-9]{0,4}$/D',$v['DB_PORT'])
            || (int)$v['DB_PORT']>65535 || !preg_match('/^[A-Za-z0-9_]+$/D',$v['DB_PREFIX'])) acc009a_fail('config_target_not_admitted');
        foreach (['DIR_OPENCART'=>'','DIR_APPLICATION'=>($app==='admin'?'adminEvhenii':'catalog'),'DIR_SYSTEM'=>'system','DIR_EXTENSION'=>'extension','DIR_CONFIG'=>'system/config','DIR_TEMPLATE'=>($app==='admin'?'adminEvhenii':'catalog').'/view/template'] as $key=>$relativePath) {
            $wanted = $relativePath===''?$root:acc009a_path($root,$relativePath);
            if (realpath($v[$key])===false || realpath($wanted)===false || realpath($v[$key])!==realpath($wanted)) acc009a_fail('configured_runtime_path_mismatch');
        }
        $configs[$app]=$v;
    }
    foreach (['DB_HOSTNAME','DB_PORT','DB_DATABASE','DB_PREFIX','DB_DRIVER'] as $key) {
        if ($configs['catalog'][$key]!==$configs['admin'][$key]) acc009a_fail('catalog_admin_db_target_mismatch');
    }
    return $configs['admin'];
}
function acc009a_sources(string $root, array $expected): array {
    if (count($expected)>96) acc009a_fail('expected_sources_cap');
    $rows=[];
    foreach ($expected as $relative=>$digest) {
        if (!is_string($digest) || !preg_match('/^[a-f0-9]{64}$/D',$digest)) acc009a_fail('invalid_expected_hash');
        $path=acc009a_path($root,$relative);
        if (is_file($path) && filesize($path)>4000000) acc009a_fail('source_size_cap');
        $actual=is_file($path)?hash_file('sha256',$path):null;
        $rows[]=['path'=>$relative,'expected_sha256'=>$digest,'actual_sha256'=>$actual,'matches'=>$actual===$digest];
    }
    foreach (['adminEvhenii/model/setting/extension.php','adminEvhenii/model/setting/setting.php'] as $relative) {
        if (isset($expected[$relative])) continue;
        $path=acc009a_path($root,$relative);
        if (is_file($path) && filesize($path)>1000000) acc009a_fail('pending_source_size_cap');
        $rows[]=['path'=>$relative,'expected_sha256'=>null,'actual_sha256'=>is_file($path)?hash_file('sha256',$path):null,'matches'=>null];
    }
    return $rows;
}
function acc009a_ocmod(string $root): array {
    $path=acc009a_path($root,'extension/ocmod');
    if (!file_exists($path)) return [];
    if (!is_dir($path)) acc009a_fail('ocmod_not_directory');
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    $rows=[]; $seen=0;
    foreach ($iterator as $file) {
        if (++$seen>2048) acc009a_fail('ocmod_inventory_cap');
        if ($file->isLink()) acc009a_fail('linked_ocmod_entry');
        if ($file->isFile() && preg_match('/\.(?:php|twig)$/iD',$file->getFilename())) {
            if ($file->getSize()>4000000 || count($rows)>=128) acc009a_fail('ocmod_source_cap');
            $rows[]=['path'=>str_replace('\\','/',substr($file->getPathname(),strlen($root)+1)),'sha256'=>hash_file('sha256',$file->getPathname())];
        }
    }
    usort($rows,static function($a,$b){return strcmp($a['path'],$b['path']);});
    return $rows;
}
function acc009a_rows(mysqli $db, string $sql, int $cap=128): array {
    if (strpos($sql,'SELECT ')!==0 || strpos($sql,';')!==false || $cap<1 || $cap>512) acc009a_fail('read_only_query_guard');
    $statement=$db->prepare($sql);
    if (!$statement) acc009a_fail('metadata_prepare_failed');
    try {
        if (!$statement->execute()) acc009a_fail('metadata_execute_failed');
        $meta=$statement->result_metadata();
        if (!$meta) acc009a_fail('metadata_result_missing');
        $bound=[]; $refs=[];
        foreach ($meta->fetch_fields() as $column) { $bound[$column->name]=null; $refs[]=&$bound[$column->name]; }
        $meta->free();
        if (!$statement->bind_result(...$refs)) acc009a_fail('metadata_bind_failed');
        $rows=[];
        while (($fetch=$statement->fetch())===true) {
            $row=[]; foreach ($bound as $key=>$value) $row[$key]=$value;
            $rows[]=$row;
            if (count($rows)>$cap) acc009a_fail('metadata_result_cap');
        }
        if ($fetch===false || $statement->errno) acc009a_fail('metadata_fetch_failed');
        return $rows;
    } finally { $statement->close(); }
}
function acc009a_identifiers(array $rows, array $fields): array {
    foreach ($rows as &$row) {
        foreach ($fields as $field) {
            if (!isset($row[$field]) || !is_string($row[$field]) || strlen($row[$field])>256
                || !preg_match('~^[A-Za-z0-9_./*|:\\-]+$~D',$row[$field])) acc009a_fail('metadata_identifier_requires_private_review');
        }
    } unset($row);
    return $rows;
}
function acc009a_metadata(array $config): array {
    if (!class_exists('mysqli')) acc009a_fail('mysqli_required');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db=@new mysqli($config['DB_HOSTNAME'],$config['DB_USERNAME'],$config['DB_PASSWORD'],$config['DB_DATABASE'],(int)$config['DB_PORT']);
    $prefix=$config['DB_PREFIX']; unset($config);
    if ($db->connect_errno || !$db->set_charset('utf8mb4')) acc009a_fail('metadata_connection_or_charset_failed');
    try {
        $schema=[];
        foreach (['event'=>['code','trigger','action','status','sort_order'], 'startup'=>['code','action','status','sort_order'], 'theme'=>['route','store_id','status'], 'extension_install'=>['code']] as $table=>$required) {
            $columns=acc009a_rows($db,"SELECT COLUMN_NAME AS field FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$db->real_escape_string($prefix.$table)."' ORDER BY ORDINAL_POSITION LIMIT 65",64);
            $names=array_column($columns,'field');
            foreach ($required as $name) if (!in_array($name,$names,true)) acc009a_fail('metadata_schema_requires_review:'.$table.'/'.$name);
            $schema[$table]=$names;
        }
        // Match actual PHP-truthy active statuses, not merely SQL status=1.
        $events=acc009a_rows($db,'SELECT code, `trigger`, action, status, sort_order FROM `'.$prefix.'event` ORDER BY sort_order, code, `trigger`, action LIMIT 513',512);
        $startups=acc009a_rows($db,'SELECT code, action, status, sort_order FROM `'.$prefix.'startup` ORDER BY sort_order, code, action LIMIT 65',64);
        $activeEvents=array_values(array_filter($events,static function($r){return (bool)$r['status'];}));
        $activeStartups=array_values(array_filter($startups,static function($r){return (bool)$r['status'];}));
        $themes=acc009a_rows($db,'SELECT route, store_id, status FROM `'.$prefix.'theme` ORDER BY store_id, route LIMIT 257',256);
        $themes=array_values(array_filter($themes,static function($r){return (bool)$r['status'];}));
        $extensions=acc009a_rows($db,'SELECT code FROM `'.$prefix.'extension_install` ORDER BY code LIMIT 129',128);
        return ['schema'=>$schema,'raw_event_rows'=>count($events),'raw_startup_rows'=>count($startups),
            'active_events'=>acc009a_identifiers($activeEvents,['code','trigger','action']),
            'active_startups'=>acc009a_identifiers($activeStartups,['code','action']),
            'active_theme_routes'=>acc009a_identifiers($themes,['route']),
            'installed_extension_codes'=>acc009a_identifiers($extensions,['code'])];
    } finally { $db->close(); }
}

function acc009s_spec(): array { return json_decode(base64_decode(ACC009S_SPEC_B64,true),true,256,JSON_THROW_ON_ERROR); }
function acc009s_log(string $key,string $value): void { echo $key.'='.$value."\n"; }
function acc009s_target(string $root,array $s): string { return acc009a_path($root,$s['target']); }
function acc009s_transform(string $before,array $s): string {
    if (hash('sha256',$before)!==$s['before_sha256']) acc009a_fail('target_base_hash_changed');
    $after=$before;
    foreach ($s['edits'] as $edit) {
        if (substr_count($after,$edit['old'])!==1) acc009a_fail('anchor_count_mismatch');
        $after=str_replace($edit['old'],$edit['new'],$after);
    }
    if (hash('sha256',$after)!==$s['after_sha256'] || strpos($after,$s['marker'])===false) acc009a_fail('payload_identity_failed');
    return $after;
}
function acc009s_lint(string $path): void {
    if (!function_exists('exec')) acc009a_fail('host_php_lint_unavailable');
    $output=[];$exit=1;
    exec(escapeshellarg(PHP_BINARY).' -n -l '.escapeshellarg($path).' 2>&1',$output,$exit);
    if ($exit!==0) acc009a_fail('host_php_lint_failed');
}
function acc009s_lint_payload(string $body): void {
    $path=tempnam(sys_get_temp_dir(),'acc009-lint-');
    if ($path===false) acc009a_fail('lint_temp_unavailable');
    try {
        if (!chmod($path,0600) || file_put_contents($path,$body,LOCK_EX)!==strlen($body)) acc009a_fail('lint_temp_write_failed');
        acc009s_lint($path);
    } finally { if (is_file($path) && !unlink($path)) acc009a_fail('lint_temp_cleanup_failed'); }
}
function acc009s_normal_events(array $rows): array {
    $out=[];
    foreach ($rows as $r) {
        if (!preg_match('~^(?:admin|system)/~D',$r['trigger'])) continue;
        $out[]=['code'=>(string)$r['code'],'trigger'=>(string)$r['trigger'],'action'=>(string)$r['action'],'status'=>(string)$r['status'],'sort_order'=>(int)$r['sort_order']];
    }
    usort($out,static function($a,$b){return [$a['sort_order'],$a['code'],$a['trigger'],$a['action'],$a['status']]<=>[$b['sort_order'],$b['code'],$b['trigger'],$b['action'],$b['status']];});
    return $out;
}
function acc009s_preflight(string $root,array $s,string $current): void {
    $expected=$s['runtime']['expected'];
    $expected[$s['target']]=hash('sha256',$current);
    foreach (acc009a_sources($root,$expected) as $r) if ($r['matches']!==true) acc009a_fail('runtime_source_changed:'.$r['path']);
    if (acc009a_ocmod($root)!==[]) acc009a_fail('generated_ocmod_requires_review');
    $meta=acc009a_metadata(acc009a_config($root));
    if (acc009s_normal_events($meta['active_events'])!==$s['runtime']['admin_events']) acc009a_fail('admin_or_system_events_changed');
    if ($meta['active_startups']!==[]) acc009a_fail('active_startup_requires_review');
    $codes=array_column($meta['installed_extension_codes'],'code');sort($codes,SORT_STRING);
    if ($codes!==$s['runtime']['extension_codes']) acc009a_fail('extension_namespace_inventory_changed');
    // Catalog-only DB events/themes are outside this admin view's admitted path.
    acc009s_log('runtime_sources','49_exact_identities_and_no_generated_ocmod');
    acc009s_log('metadata','admin_system_events_and_namespace_inventory_unchanged');
}
function acc009s_private_base(string $root): string {
    $parent=realpath(dirname($root));
    if (!$parent || $parent===$root) acc009a_fail('private_backup_parent_unavailable');
    $base=$parent.'/_patch_backups';
    if (is_link($base) || (file_exists($base) && !is_dir($base))) acc009a_fail('unsafe_backup_base');
    if (!is_dir($base) && !mkdir($base,0700)) acc009a_fail('backup_base_create_failed');
    return $base;
}
function acc009s_backup(string $root,array $s,string $before): string {
    $base=acc009s_private_base($root);
    $name=$s['id'].'-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));
    $dir=$base.'/'.$name;
    if (!mkdir($dir,0700)) acc009a_fail('backup_directory_create_failed');
    $path=$dir.'/internet_document.php';
    if (file_put_contents($path,$before,LOCK_EX)!==strlen($before) || !chmod($path,0600) || hash_file('sha256',$path)!==$s['before_sha256']) acc009a_fail('backup_verify_failed');
    $manifest=json_encode(['id'=>$s['id'],'target'=>$s['target'],'before_sha256'=>$s['before_sha256'],'after_sha256'=>$s['after_sha256']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    if (file_put_contents($dir.'/manifest.json',$manifest,LOCK_EX)!==strlen($manifest) || !chmod($dir.'/manifest.json',0600)) acc009a_fail('backup_manifest_failed');
    acc009s_log('backup_path','../_patch_backups/'.$name);
    return $dir;
}
function acc009s_replace(string $path,string $body,int $mode): void {
    if (is_link($path) || !is_file($path)) acc009a_fail('target_missing_or_linked');
    $temp=tempnam(dirname($path),'.acc009-');
    if ($temp===false) acc009a_fail('atomic_temp_unavailable');
    try {
        if (!chmod($temp,0600) || file_put_contents($temp,$body,LOCK_EX)!==strlen($body)
            || !chmod($temp,$mode) || !rename($temp,$path)) acc009a_fail('atomic_replace_failed');
    } finally { if (is_file($temp)) unlink($temp); }
}
function acc009s_apply(string $root,array $s,string $before,string $after): string {
    $path=acc009s_target($root,$s);$mode=fileperms($path)&0777;
    $dir=acc009s_backup($root,$s,$before);
    if (hash_file('sha256',$path)!==$s['before_sha256']) acc009a_fail('target_changed_after_backup');
    try {
        acc009s_replace($path,$after,$mode);
        acc009s_lint($path);
        if (hash_file('sha256',$path)!==$s['after_sha256']) acc009a_fail('written_target_hash_failed');
    } catch (Throwable $e) {
        $actual=hash_file('sha256',$path);
        if ($actual!==$s['before_sha256'] && $actual!==$s['after_sha256']) acc009a_fail('unexpected_concurrent_target_change_backup_retained');
        if ($actual!==$s['before_sha256']) acc009s_replace($path,$before,$mode);
        if (hash_file('sha256',$path)!==$s['before_sha256']) acc009a_fail('restore_failed_backup_retained');
        acc009s_log('restored','yes');
        acc009a_fail('apply_failed_original_restored');
    }
    acc009s_log('changed_file',$s['target']);acc009s_log('host_php_lint','ok');
    return $dir;
}
function acc009s_rollback(string $root,array $s,string $name): void {
    if (!preg_match('/^'.preg_quote($s['id'],'/').'\-[0-9]{8}T[0-9]{6}Z\-[a-f0-9]{8}$/D',$name)) acc009a_fail('invalid_backup_name');
    $dir=acc009s_private_base($root).'/'.$name;
    if (is_link($dir) || !is_dir($dir) || is_link($dir.'/internet_document.php') || is_link($dir.'/manifest.json')) acc009a_fail('unsafe_backup_path');
    if (!is_file($dir.'/manifest.json') || filesize($dir.'/manifest.json')>2048 || !is_file($dir.'/internet_document.php') || filesize($dir.'/internet_document.php')>4000000) acc009a_fail('backup_missing_or_size_cap');
    $m=json_decode(file_get_contents($dir.'/manifest.json'),true,16,JSON_THROW_ON_ERROR);
    if ($m!==['id'=>$s['id'],'target'=>$s['target'],'before_sha256'=>$s['before_sha256'],'after_sha256'=>$s['after_sha256']]) acc009a_fail('backup_manifest_mismatch');
    $before=file_get_contents($dir.'/internet_document.php');
    if (!is_string($before) || hash('sha256',$before)!==$s['before_sha256']) acc009a_fail('backup_bytes_mismatch');
    $path=acc009s_target($root,$s);
    if (!is_file($path) || hash_file('sha256',$path)!==$s['after_sha256']) acc009a_fail('rollback_current_hash_changed_or_reverse_N3_first');
    acc009s_lint_payload($before);
    $after=file_get_contents($path);$mode=fileperms($path)&0777;
    acc009s_replace($path,$before,$mode);
    try { acc009s_lint($path); } catch (Throwable $e) { acc009s_replace($path,$after,$mode);acc009a_fail('rollback_lint_failed_restored_afterimage'); }
    if (hash_file('sha256',$path)!==$s['before_sha256']) acc009a_fail('rollback_hash_failed');
    acc009s_log('restored_file',$s['target']);acc009s_log('host_php_lint','ok');
}
// ACC009S_ENTRY: fixture harness uses complete owned helpers before this entry.
if (PHP_SAPI!=='cli') {
    $nonce=$_GET['acc009_probe']??null;
    if (($_SERVER['REQUEST_METHOD']??'')!=='GET' || !is_string($nonce) || !preg_match('/^[a-f0-9]{32}$/D',$nonce)) { http_response_code(404);exit; }
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, max-age=0');header('X-Content-Type-Options: nosniff');
    $s=acc009s_spec();
    echo json_encode(['task'=>$s['id'].'-web-runtime','probe_nonce'=>$nonce,'runtime'=>['php_version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'opcache'=>['enabled'=>ini_get('opcache.enable')==='1','validate_timestamps'=>ini_get('opcache.validate_timestamps')==='1','revalidate_freq'=>(int)ini_get('opcache.revalidate_freq')]]],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
}
ini_set('display_errors','0');
set_error_handler(static function($n,$m,$f,$l){if(error_reporting()&$n)throw new Acc009AdmissionFailure('runtime_warning_details_withheld');return true;});
try {
    if (PHP_VERSION_ID<80000 || PHP_VERSION_ID>=80100) acc009a_fail('production_PHP8_0_CLI_required');
    $s=acc009s_spec();$root=realpath(getcwd());
    if (!$root || basename($root)!=='public_html') acc009a_fail('run_from_public_html');
    $self=realpath(__FILE__);
    if (!$self || !in_array(dirname($self),[$root,dirname($root)],true)) acc009a_fail('runner_must_be_inside_or_beside_webroot');
    $mode=$argv[1]??'--dry-run';
    if (count($argv)>2 || !($mode==='--dry-run' || $mode==='--apply' || strpos($mode,'--rollback=')===0)) acc009a_fail('mode_requires_dry_run_apply_or_rollback');
    acc009s_log('task',$s['id']);acc009s_log('cwd',$root);acc009s_log('time',gmdate('c'));acc009s_log('mode',$mode);
    $path=acc009s_target($root,$s);
    if (!is_file($path) || filesize($path)>4000000) acc009a_fail('target_missing_or_size_cap');
    $before=file_get_contents($path);if(!is_string($before))acc009a_fail('target_read_failed');
    if (strpos($mode,'--rollback=')===0) { acc009s_rollback($root,$s,substr($mode,11)); }
    elseif (in_array(hash('sha256',$before),$s['already_hashes'],true) && strpos($before,$s['marker'])!==false) {
        acc009s_log('already_applied','yes');
    } else {
        $after=acc009s_transform($before,$s);
        acc009s_preflight($root,$s,$before);acc009s_lint_payload($after);
        acc009s_log('payload_php_lint','ok');
        if ($mode==='--apply') acc009s_apply($root,$s,$before,$after);
        else acc009s_log('source_preflight','ok_web_runtime_and_owner_apply_gate_remain');
    }
    if ($mode!=='--dry-run') { if(!unlink(__FILE__))acc009a_fail('runner_self_delete_failed');acc009s_log('self_deleted','yes'); }
    acc009s_log('done','ok');
} catch (Acc009AdmissionFailure $e) { fwrite(STDERR,'error='.$e->getMessage()."\n");exit(1); }
catch (Throwable $e) { fwrite(STDERR,"error=unexpected_patch_failure_details_withheld\n");exit(1); }
