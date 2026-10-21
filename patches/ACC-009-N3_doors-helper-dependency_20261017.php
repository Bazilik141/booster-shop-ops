<?php
/** ACC-009 guarded single-file source patch, PHP 8.0.
 * Default/--dry-run: current source/metadata checks and native lint, no source/DB writes.
 * --apply: OWNER ONLY, after fresh web-runtime gate; backup, atomic replace, lint, restore-on-fail.
 * --rollback=NAME: OWNER ONLY; exact known current bytes + private verified backup, reverse N3 before N1.
 * No runner SQL mutation. File rollback cannot undo remote NP objects created by later operator actions.
 * Web branch exposes only runtime version/OPcache flags for fresh owner admission; no config/bootstrap/NP.
 */
const ACC009S_SPEC_B64 = 'eyJpZCI6IkFDQy0wMDktTjMiLCJ0YXJnZXQiOiJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL2FkbWluL2NvbnRyb2xsZXIvc2hpcHBpbmcvaW50ZXJuZXRfZG9jdW1lbnQucGhwIiwibWFya2VyIjoiQUNDLTAwOS1OMyIsImJlZm9yZV9zaGEyNTYiOiI0M2RjNDY2ZjUzMzJkNDVhY2VjYzVlMWNiMmU4MmU4ZjcxMjI2OGFmNTY4ZDZmMTZkZTkwODA5YTNkZDYwZGExIiwiYWZ0ZXJfc2hhMjU2IjoiMjEzOGUxNTY0M2NlNGJjZWQxYjQ5Njk4OTU1Yzc4ZjQ0MzYxN2U0YjU5ODc3MzI1MTEzMjYxMDQyZDFjN2I5MyIsImFscmVhZHlfaGFzaGVzIjpbIjIxMzhlMTU2NDNjZTRiY2VkMWI0OTY5ODk1NWM3OGY0NDM2MTdlNGI1OTg3NzMyNTExMzI2MTA0MmQxYzdiOTMiXSwiZWRpdHMiOlt7Im9sZCI6IiAgICAgICAgICAgICRjb3VudGVycGFydHlfYWRkcmVzc19hcGlfcmVzdWx0ID0gJFBpbnRhQ291bnRlcnBhcnR5LT5zYXZlQWRkcmVzcygkY291bnRlcnBhcnR5X3JlZiwgJHN0cmVldFsncmVmJ10sICRob3VzZSwgJGZsYXQpOyIsIm5ldyI6IiAgICAgICAgICAgIC8vIEFDQy0wMDktTjM6IGluaXRpYWxpemUgdGhlIGhlbHBlciBkZXBlbmRlbmN5IG9ubHkgYWZ0ZXIgc3VjY2Vzc2Z1bCBsb29rdXBzLlxuICAgICAgICAgICAgJFBpbnRhQ291bnRlcnBhcnR5ID0gbmV3IFxcT3BlbmNhcnRcXFN5c3RlbVxcTGlicmFyeVxcUGludGFub3ZhcG9zaHRhXFxQaW50YUNvdW50ZXJwYXJ0eSgpO1xuICAgICAgICAgICAgJGNvdW50ZXJwYXJ0eV9hZGRyZXNzX2FwaV9yZXN1bHQgPSAkUGludGFDb3VudGVycGFydHktPnNhdmVBZGRyZXNzKCRjb3VudGVycGFydHlfcmVmLCAkc3RyZWV0WydyZWYnXSwgJGhvdXNlLCAkZmxhdCk7In1dLCJydW50aW1lIjp7ImV4cGVjdGVkIjp7ImFkbWluRXZoZW5paS9jb250cm9sbGVyL2V2ZW50L2xhbmd1YWdlLnBocCI6ImViYzAxMWJlMjFhYTc4Zjc2NzFkZDU0NDg0ZTdkZmRlZmQxNDQxYzRiMDM0MjRkM2EzZjdjNDViMDVjODI0M2YiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9ldmVudC9tb2RpZmljYXRpb24ucGhwIjoiMTdhYmE3MGQ1NTdkNWE4NjNlZjY1NTBmZGY5NzNlZDFiMmRkNmIwYjBmODRiYTY3NjFmNzAxNzgzYjY1MzVkMCIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL3N0YXJ0dXAvYXBwbGljYXRpb24ucGhwIjoiZGM4MzMyMDIyN2ViZTljN2M4NTZkMmJlMGQ2MzFiNjBiOGY3ZGQ3YWY0YzU5YjYzZDYzZWUwNDU1MjVhNTc2OSIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL3N0YXJ0dXAvYXV0aG9yaXplLnBocCI6ImQ3YzI1NjY0MjJhYmVlM2ViMjBmNzdlMTdjNTg4ODNiMjM4NDI0NGVlY2U5OGEyMDQ3MmIwYmRhN2FjZTFhMDAiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9zdGFydHVwL2Vycm9yLnBocCI6IjdkNzE0YjliMDM0NjA5ZWE4ZGZiOTUxZTc4OTdiYjgzNmYxODcyOTRlMDQ3NmE4YTVhMzgxMzQwYzViMzQ2OGMiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9zdGFydHVwL2V2ZW50LnBocCI6ImI5MDZmZmUzNzIyZjU4NDY3OTg4ZGZhNzY4YTZlYTNlY2M1NzgzYjdiYTFmYzhlZTMxOGJiN2M1MzVhYjE4MDAiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9zdGFydHVwL2V4dGVuc2lvbi5waHAiOiI0NTE0NjhkNTE1N2E3Yzk3MjljNjEzZmRhYzFhMGQ2MTgzM2M2ZTIwMGM1M2U0YThhZGYyYmNkMmE5NDFiODUxIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9sYW5ndWFnZS5waHAiOiJhNzU4OGZiNzA1NTNmZjMxMWFjYjJjZTI0MzUyZjZiZTk4MzA0NGU3NGVmNzQ4ZDc2MDRjMDAwZmU1NTkwNjQzIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9sb2dpbi5waHAiOiJhNjNjNmFiOGM5ZGNjMzRlM2UwM2E2MmVmZTliOGY0ZGE4OWVlMDEzZmFmZmVlODlhM2FhMzg1YjM3MmY2NTgwIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9wZXJtaXNzaW9uLnBocCI6ImJjOGUyMWY3NzM0MTQ5NzkzMjA3NjZhZjA0MjYzYTM0MDA5ZTA1ZDNmNzAyZGY2NThmMjBjZGM5MzI3M2QxNjkiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9zdGFydHVwL3Nhc3MucGhwIjoiNTUwMTc3NzFmYWI5NTI1ZmI3M2M4MTRjNzhmODI3YmU3MmRmOGZmZjkyMzljYjE5NTY2Yzk1OTNmYjAxNGIwYyIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL3N0YXJ0dXAvc2Vzc2lvbi5waHAiOiJkOTEzNWYzN2UzNjFmOTFmM2IxODJlZmNmZjU0OTAwYmYyNGFjODI0NjMzZDE4MmFkOTNmZWY0OTVlY2Y5YmEyIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9zZXR0aW5nLnBocCI6ImExMGZmNmI4NTg3MzI0OGU0YWU3MmQ3OTlmNjJiMTg2YjFlM2M1ZWEzYTMwNDNlNzc0NGQ5NmNmYTJiYjIxYmQiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9zdGFydHVwL3N0YXJ0dXAucGhwIjoiMGRmMmFlZGU0ZjIwNTlmZDliN2U0YjVlNmE2ZTEyNTg2YWM2MWZkYzIwYmEyYzc1MDMxMjRmYzUwZTVkMGRkMiIsImFkbWluRXZoZW5paS9pbmRleC5waHAiOiIxNGY0NDJiYmYxODdmNTJiOWQ5OTdmZjgzYjMzNTg2MTJmYmU4NWUwMzBmNDk0Y2EwZTA4NGI1MjBiZTg1YWQzIiwiYWRtaW5FdmhlbmlpL21vZGVsL3NhbGUvb3JkZXIucGhwIjoiZTkwMDVlYjQ1OTdmNjkwM2JlMTMzZjRkMDA3N2QwZDRkNjhhMWE2MzcyZjY4MzU3Y2U1YzI1Y2U4ZDg1N2I1ZCIsImFkbWluRXZoZW5paS9tb2RlbC9zZXR0aW5nL2V2ZW50LnBocCI6IjM3ODYxZmRhZWZhODRjODE2ZWJmMzEzYzM3ZmZiMzMzZTIzM2ViNzFhNWZhYTk4OTgwYzhjYzc5MTk1ZWNiMjQiLCJhZG1pbkV2aGVuaWkvbW9kZWwvc2V0dGluZy9zdGFydHVwLnBocCI6Ijg5OWYwNTVlMWI0MzQyNGNiMDNiYzU0Zjg0MmEwMDY0OWY5NTRlOTNkMGUwMDYyN2EyZWYxYjBhZWU1ZGMwNzMiLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL2FkbWluL2NvbnRyb2xsZXIvcGF5bWVudC9waW50YV9ub3ZhX3Bvc2h0YV9jb2QucGhwIjoiMzNkNTU0MTA4ZmIxMGY2NDgyNDY5YmE1NGExMmU2ZjE3ODQ3NTdkMWNmYTQxMTAzYjNhNGFkNDk4YjNiNzdiYyIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2QvYWRtaW4vY29udHJvbGxlci9zaGlwcGluZy9pbnRlcm5ldF9kb2N1bWVudC5waHAiOiI4ZTA3OTQ1ZGFjYzViM2ZhYjI2ODEyNjZhYzRkMDM4MTFlOWI3MDJkMjJiMDY3MDA4ZDAyYzFkNzFjNTlkNTY3IiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9hZG1pbi9jb250cm9sbGVyL3NoaXBwaW5nL3BpbnRhX25vdmFfcG9zaHRhLnBocCI6ImU5ZTMwMDZiNzlkNDBmNjVkZDM4MTc1YzgwOWY4YWJmYjUwZDAwM2RhZjExMzA3MGU0NmQ3YmY2MWYyMDNkZDMiLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL2FkbWluL2NvbnRyb2xsZXIvc2hpcHBpbmcvcGludGFfbm92YV9wb3NodGEvcGludGFfdmFsaWRhdGVfYXBpX3Jlc3VsdF90cmFpdC5waHAiOiIwM2ViMzBkMGMzZjI0YTE3OTEyZmRmY2NkYzdmMzIwMzU3YzEwNzQxN2JhNDUwZjVkOTNiZGE3YmUwMWE2ZWIzIiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9hZG1pbi9tb2RlbC9tb2R1bGUvY2l0eS5waHAiOiI3NTQ1MGFhZGQ4MDcyOGEwNzllMTRhNjlhY2Q0ZWY5ODU5OTk1NmY3ZDlhNzNlNDRhZDQ2MGM0ZmY0MmUyNGQ2IiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9hZG1pbi9tb2RlbC9tb2R1bGUvY291bnRlcnBhcnR5LnBocCI6IjRhYzE2YjQ3MDRhOTg0NjMxNDlmMDg3ODA1MDRkMjE4YWFhZjZiNGM4MmUxNGFjYTI2YjBiMzIwNDYwMjBhZTYiLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL2FkbWluL21vZGVsL21vZHVsZS9pbnRlcm5ldF9kb2N1bWVudC5waHAiOiJmZTE5OWRkYjIyNmYwZDlhNzU1NTllNWRjZmY4Y2M1MmJlOTMzMDA1MjdmMWRjNjU2OGI3YTlhNWE4MGQxMTk3IiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9hZG1pbi9tb2RlbC9tb2R1bGUvc3RyZWV0LnBocCI6IjhiMDQ2Njk5ZTYzMDBiZjEwZTM1ZjExNzI5MjI2MDQ0ZTllNzM1ZDhmZjY5NzVhMWY1MmIwZWU0YzExYzNhNWEiLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL2FkbWluL3ZpZXcvamF2YXNjcmlwdC9waW50YV9ub3ZhX3Bvc2h0YS9jb21tb24uanMiOiI5YWU1YWJkMGE0YTFlYjc5N2ZlZTAzNzIzZGQ2ODhmYjc3MmYxNWY1ZGNkYzE3NTZhM2U4ZDk2NmQwYmZlYTY5IiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9hZG1pbi92aWV3L3RlbXBsYXRlL3NoaXBwaW5nL3BpbnRhX25vdmFfcG9zaHRhL2NyZWF0ZV9pbnRlcm5ldF9kb2N1bWVudC50d2lnIjoiNjg0MjQ3MDRmNTI5OGE5ZjBjODBlZGYzNTcyN2RlY2MzN2E2OWZmNzE5MTM2MDQ5ODQxYTFlZjNhNDAyYmFjNSIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2Qvc3lzdGVtL2xpYnJhcnkvcGludGFub3ZhcG9zaHRhL3BpbnRhY291bnRlcnBhcnR5LnBocCI6IjQ0ZDEzOGVkY2EzNWI4M2ViN2UxMjQxNjFjZjhmYzBmYzE5ZDA4NGRiOGE2MzVmMzJiOTAxY2MwMGI3NzNiYjciLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL3N5c3RlbS9saWJyYXJ5L3BpbnRhbm92YXBvc2h0YS9waW50YWludGVybmV0ZG9jdW1lbnQucGhwIjoiMGE5MjE2ZTVhYjQ4ZGFhYzhmYWE1ODUxYWQzOWNjMGRkZWY5NTE5ZTZlM2ZiYWFlNGY3N2QyMjFkMTNiMDBjYyIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2Qvc3lzdGVtL2xpYnJhcnkvcGludGFub3ZhcG9zaHRhL3BpbnRhbm92YXBvc2h0YWFwaS5waHAiOiI3MjljM2M2Y2VlNmIwNGY5MjU5NjdhOWU0MjI3N2I5NzAyZTUxODc2YjM3ZDZkZjExOWJhNDAyNThiMjBiMTljIiwic3lzdGVtL2NvbmZpZy9hZG1pbi5waHAiOiIxYTk4NmRmOGNiNWY1YmEyMmQ4MmFhZGIzZmM0ZDA1OTYwYWJjMjBjNmExMDk1NDg2YzRhMzBjNjEzMzFmOWFmIiwic3lzdGVtL2NvbmZpZy9kZWZhdWx0LnBocCI6ImM1NmQ0MWQwODMzNmFhYzZkNjUyYTNiNWVjZDg3YTExYmUwNTMzNGEyMmI0ZmY5MDE3ZmEwM2RiZWFmNzA4NzAiLCJzeXN0ZW0vZW5naW5lL2FjdGlvbi5waHAiOiI5MWE0YWYzNzc1ZTljMWM1OTlmYWY1NWYyYjFmOWVhYzg5Yjg5ZDE3ODY1N2M2YmQ4ZWM3YjYxMDU0MzczZTc5Iiwic3lzdGVtL2VuZ2luZS9hdXRvbG9hZGVyLnBocCI6ImMzMTBmMWNhYzUzMTgwYjMwMWRkMDQ4ZjAxNzVhMzU5YmVlMWNjZTcwZjRjOTQyODUxYjhhZDMwNTM5Zjk5YjMiLCJzeXN0ZW0vZW5naW5lL2NvbmZpZy5waHAiOiJkMWE3MjQwN2M3N2Y2ZGRjNDQ4Y2NhZTkwZDQ4OWNkNTkwN2U1Y2I0MTA4ZTdkZWNjYTRiNDZlYjM1NDFiZDBkIiwic3lzdGVtL2VuZ2luZS9jb250cm9sbGVyLnBocCI6IjhhNTFlZjExMjRkYTNiOWI2YWU5MWIyZWM5MTRkZjFmMmJiZTk1MTQyYjc1MzQzMWY3Y2ZhMGU3NTBjYmUwM2IiLCJzeXN0ZW0vZW5naW5lL2V2ZW50LnBocCI6IjdjMTMyYTRkZDE3NDkyMTQ2MWI0Nzc0NjQ3YWVlN2Y0YjM5NzRjNTNmYWJlODRlNmEwY2NkNzRiMGQ3Mzc1MjQiLCJzeXN0ZW0vZW5naW5lL2ZhY3RvcnkucGhwIjoiZmQ4YTg5NDA5MTVkOWFlM2RiMmMwYmNkNWNlYmUyMjU0NGY2NTMyNTcyNjQ3ODAwNjZlM2E1YTJhZTdjZjdmZSIsInN5c3RlbS9lbmdpbmUvbG9hZGVyLnBocCI6ImVhMjA5ODQ1MGRkYzU2Nzc0N2UyZDg5MDAxZDk5N2NlOTIxZTk2NmEzMTNjYWZiNjgwYTMwOGVlMzY5YTQ3MjMiLCJzeXN0ZW0vZW5naW5lL21vZGVsLnBocCI6ImNkM2UwNDI2YmY4MGMwMGU2NTRiYTUxOGZhNGMwYjNlODQ2ODY2NmMzOWY4NjAxMWUxMTAyOWVjYmUyOGQ5NTAiLCJzeXN0ZW0vZW5naW5lL3Byb3h5LnBocCI6IjU2OWFmOGI1MDkwOWJlMjVkY2QzN2Y2ZDhhZmMzNDc4YWRhMDhhZjNkNTlkM2VjNWQxNjYxODc2NjJjYTQ0ZDkiLCJzeXN0ZW0vZW5naW5lL3JlZ2lzdHJ5LnBocCI6IjMyNWM2ZTBmNjdmZGZkYTIwOTI2NzA0ODViM2FjMzhmY2M5MTE2Nzg5MGMyNTMxNjA5YWNhZWI4NzM5MzM2MzciLCJzeXN0ZW0vZnJhbWV3b3JrLnBocCI6IjdiZDIxZjliMmQ5MTY3YjNmNzBjMTg5OTY4N2MxODdkNzY5MDIyNDQzZDIwOTFhM2RlOTMwYTQxYzE0YjUwODgiLCJzeXN0ZW0vbGlicmFyeS9yZXF1ZXN0LnBocCI6IjQ4M2MxYTQ0ZWMwYzdjOWQ2OTY5NTcwZDc2NmMxY2Q4YzQwZTc2NDZkMTlkYTBmN2ExMWQwZGRhNzk3OWY1MmMiLCJzeXN0ZW0vbGlicmFyeS90ZW1wbGF0ZS5waHAiOiI3ZmRlNmI5NTY5YWE4Nzk0ZjE0ZGVlOGM4YzA2Nzc1OTE0NTMzNDJkZWZlMDg4MmMyMDhkNGI2ODk2YjMwNmQwIiwic3lzdGVtL2xpYnJhcnkvdGVtcGxhdGUvdHdpZy5waHAiOiI5MmE2YWMwYWY2ZmY4ODA1M2VjYWZlMGFjZDg2ZTI0OWJkZGFhYjY2NDA4MGE1NDljMGE1MDIxNzlmNTM3YTY5IiwiYWRtaW5FdmhlbmlpL21vZGVsL3NldHRpbmcvZXh0ZW5zaW9uLnBocCI6IjY3ZWRiN2ViNzM5OWYzNWRlNTExZGIzMjM5ODE1ZTQzYzRiZjEyNzE3NzFkNzliZWQ2M2NmOWYwZTU4ZjJkZmIiLCJhZG1pbkV2aGVuaWkvbW9kZWwvc2V0dGluZy9zZXR0aW5nLnBocCI6IjQyMzE1YjY4NWQ4OWEyNmZmY2M5ODljNWRlOTk5YWVkNzM2MDQ0YjM1NjBkOTM0NGRkNGQ4ZDUyOGVhNWExZDcifSwiYWRtaW5fZXZlbnRzIjpbeyJjb2RlIjoiYW5hbHl0aWNzX3BzX2VuaGFuY2VkX21lYXN1cmVtZW50IiwidHJpZ2dlciI6ImFkbWluL2NvbnRyb2xsZXIvc2FsZS9vcmRlci5jYWxsL2FmdGVyIiwiYWN0aW9uIjoiZXh0ZW5zaW9uL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50L2FuYWx5dGljcy9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC5ldmVudEFkbWluQ29udHJvbGxlclNhbGVPcmRlckFkZEhpc3RvcnlBZnRlciIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjowfSx7ImNvZGUiOiJhbmFseXRpY3NfcHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQiLCJ0cmlnZ2VyIjoiYWRtaW4vdmlldy9zYWxlL29yZGVyX2luZm8vYmVmb3JlIiwiYWN0aW9uIjoiZXh0ZW5zaW9uL3BzX2VuaGFuY2VkX21lYXN1cmVtZW50L2FuYWx5dGljcy9wc19lbmhhbmNlZF9tZWFzdXJlbWVudC5ldmVudEFkbWluVmlld1NhbGVPcmRlckluZm9CZWZvcmUiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MH0seyJjb2RlIjoiY2FwdGNoYV9wc19nb29nbGVfcmVjYXB0Y2hhIiwidHJpZ2dlciI6ImFkbWluL2NvbnRyb2xsZXIvY29tbW9uL2xvZ2luLmxvZ2luL2FmdGVyIiwiYWN0aW9uIjoiZXh0ZW5zaW9uL3BzX2dvb2dsZV9yZWNhcHRjaGEvY2FwdGNoYS9wc19nb29nbGVfcmVjYXB0Y2hhLmV2ZW50QWRtaW5Db250cm9sbGVyQ29tbW9uTG9naW5Mb2dpbkFmdGVyIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjB9LHsiY29kZSI6ImNhcHRjaGFfcHNfZ29vZ2xlX3JlY2FwdGNoYSIsInRyaWdnZXIiOiJhZG1pbi92aWV3L2NvbW1vbi9oZWFkZXIvYmVmb3JlIiwiYWN0aW9uIjoiZXh0ZW5zaW9uL3BzX2dvb2dsZV9yZWNhcHRjaGEvY2FwdGNoYS9wc19nb29nbGVfcmVjYXB0Y2hhLmV2ZW50QWRtaW5WaWV3Q29tbW9uSGVhZGVyQmVmb3JlIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjB9LHsiY29kZSI6ImNhcHRjaGFfcHNfZ29vZ2xlX3JlY2FwdGNoYSIsInRyaWdnZXIiOiJhZG1pbi92aWV3L2NvbW1vbi9sb2dpbi9iZWZvcmUiLCJhY3Rpb24iOiJleHRlbnNpb24vcHNfZ29vZ2xlX3JlY2FwdGNoYS9jYXB0Y2hhL3BzX2dvb2dsZV9yZWNhcHRjaGEuZXZlbnRBZG1pblZpZXdDb21tb25Mb2dpbkJlZm9yZSIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjowfSx7ImNvZGUiOiJjYXB0Y2hhX3BzX2dvb2dsZV9yZWNhcHRjaGEiLCJ0cmlnZ2VyIjoiYWRtaW4vdmlldy9zZXR0aW5nL3NldHRpbmcvYmVmb3JlIiwiYWN0aW9uIjoiZXh0ZW5zaW9uL3BzX2dvb2dsZV9yZWNhcHRjaGEvY2FwdGNoYS9wc19nb29nbGVfcmVjYXB0Y2hhLmV2ZW50QWRtaW5WaWV3U2V0dGluZ1NldHRpbmdCZWZvcmUiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MH0seyJjb2RlIjoibW9kdWxlX3BzX3Byb2R1Y3RfY2F0ZWdvcnlfZmlsdGVyIiwidHJpZ2dlciI6ImFkbWluL21vZGVsL2NhdGFsb2cvcHJvZHVjdC5lZGl0UHJvZHVjdC9hZnRlciIsImFjdGlvbiI6ImV4dGVuc2lvbi9wc19wcm9kdWN0X2NhdGVnb3J5X2ZpbHRlci9tb2R1bGUvcHNfcHJvZHVjdF9jYXRlZ29yeV9maWx0ZXIuZXZlbnRBZG1pbk1vZGVsQ2F0YWxvZ1Byb2R1Y3RFZGl0UHJvZHVjdEFmdGVyIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjB9LHsiY29kZSI6Im1vZHVsZV9wc19wcm9kdWN0X2NhdGVnb3J5X2ZpbHRlciIsInRyaWdnZXIiOiJhZG1pbi92aWV3L2NhdGFsb2cvY2F0ZWdvcnlfZm9ybS9iZWZvcmUiLCJhY3Rpb24iOiJleHRlbnNpb24vcHNfcHJvZHVjdF9jYXRlZ29yeV9maWx0ZXIvbW9kdWxlL3BzX3Byb2R1Y3RfY2F0ZWdvcnlfZmlsdGVyLmV2ZW50QWRtaW5WaWV3Q2F0YWxvZ0NhdGVnb3J5Rm9ybUJlZm9yZSIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjowfSx7ImNvZGUiOiJhZG1pbl9jdXJyZW5jeV9hZGQiLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvbG9jYWxpc2F0aW9uL2N1cnJlbmN5LmFkZEN1cnJlbmN5L2FmdGVyIiwiYWN0aW9uIjoiZXZlbnQvY3VycmVuY3kiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoiYWRtaW5fY3VycmVuY3lfZWRpdCIsInRyaWdnZXIiOiJhZG1pbi9tb2RlbC9sb2NhbGlzYXRpb24vY3VycmVuY3kuZWRpdEN1cnJlbmN5L2FmdGVyIiwiYWN0aW9uIjoiZXZlbnQvY3VycmVuY3kiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoiYWRtaW5fY3VycmVuY3lfc2V0dGluZyIsInRyaWdnZXIiOiJhZG1pbi9tb2RlbC9zZXR0aW5nL3NldHRpbmcuZWRpdFNldHRpbmcvYWZ0ZXIiLCJhY3Rpb24iOiJldmVudC9jdXJyZW5jeSIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjoxfSx7ImNvZGUiOiJhZG1pbl9tYWlsX2FmZmlsaWF0ZV9hcHByb3ZlIiwidHJpZ2dlciI6ImFkbWluL21vZGVsL2N1c3RvbWVyL2N1c3RvbWVyX2FwcHJvdmFsLmFwcHJvdmVBZmZpbGlhdGUvYWZ0ZXIiLCJhY3Rpb24iOiJtYWlsL2FmZmlsaWF0ZS5hcHByb3ZlIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX21haWxfYWZmaWxpYXRlX2RlbnkiLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvY3VzdG9tZXIvY3VzdG9tZXJfYXBwcm92YWwuZGVueUFmZmlsaWF0ZS9hZnRlciIsImFjdGlvbiI6Im1haWwvYWZmaWxpYXRlLmRlbnkiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoiYWRtaW5fbWFpbF9jdXN0b21lcl9hcHByb3ZlIiwidHJpZ2dlciI6ImFkbWluL21vZGVsL2N1c3RvbWVyL2N1c3RvbWVyX2FwcHJvdmFsLmFwcHJvdmVDdXN0b21lci9hZnRlciIsImFjdGlvbiI6Im1haWwvY3VzdG9tZXIuYXBwcm92ZSIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjoxfSx7ImNvZGUiOiJhZG1pbl9tYWlsX2N1c3RvbWVyX2RlbnkiLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvY3VzdG9tZXIvY3VzdG9tZXJfYXBwcm92YWwuZGVueUN1c3RvbWVyL2FmdGVyIiwiYWN0aW9uIjoibWFpbC9jdXN0b21lci5kZW55Iiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX21haWxfY3VzdG9tZXJfdHJhbnNhY3Rpb24iLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvY3VzdG9tZXIvY3VzdG9tZXIuYWRkVHJhbnNhY3Rpb24vYWZ0ZXIiLCJhY3Rpb24iOiJtYWlsL3RyYW5zYWN0aW9uIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX21haWxfZ2RwciIsInRyaWdnZXIiOiJhZG1pbi9tb2RlbC9jdXN0b21lci9nZHByLmVkaXRTdGF0dXMvYWZ0ZXIiLCJhY3Rpb24iOiJtYWlsL2dkcHIiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoiYWRtaW5fbWFpbF9yZXR1cm4iLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvc2FsZS9yZXR1cm5zLmFkZEhpc3RvcnkvYWZ0ZXIiLCJhY3Rpb24iOiJtYWlsL3JldHVybnMiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoiYWRtaW5fbWFpbF9yZXdhcmQiLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvY3VzdG9tZXIvY3VzdG9tZXIuYWRkUmV3YXJkL2FmdGVyIiwiYWN0aW9uIjoibWFpbC9yZXdhcmQiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoiYWRtaW5fbWFpbF91c2VyX2F1dGhvcml6ZSIsInRyaWdnZXIiOiJhZG1pbi9jb250cm9sbGVyL2NvbW1vbi9hdXRob3JpemUuc2VuZC9hZnRlciIsImFjdGlvbiI6Im1haWwvYXV0aG9yaXplIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6ImFkbWluX21haWxfdXNlcl9hdXRob3JpemVfcmVzZXQiLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvdXNlci91c2VyLmFkZFRva2VuL2FmdGVyIiwiYWN0aW9uIjoibWFpbC9hdXRob3JpemUucmVzZXQiLCJzdGF0dXMiOiIxIiwic29ydF9vcmRlciI6MX0seyJjb2RlIjoiYWRtaW5fbWFpbF91c2VyX2ZvcmdvdHRlbiIsInRyaWdnZXIiOiJhZG1pbi9tb2RlbC91c2VyL3VzZXIuYWRkVG9rZW4vYWZ0ZXIiLCJhY3Rpb24iOiJtYWlsL2ZvcmdvdHRlbiIsInN0YXR1cyI6IjEiLCJzb3J0X29yZGVyIjoxfSx7ImNvZGUiOiJzdGF0aXN0aWNzX3JldHVybl9kZWxldGUiLCJ0cmlnZ2VyIjoiYWRtaW4vbW9kZWwvc2FsZS9yZXR1cm5zLmRlbGV0ZVJldHVybi9hZnRlciIsImFjdGlvbiI6ImV2ZW50L3N0YXRpc3RpY3MuZGVsZXRlUmV0dXJuIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjF9LHsiY29kZSI6InBpbnRhX25vdmFfcG9zaHRhX2FkbWluX2NvbnRyb2xsZXJfc2FsZV9vcmRlcl9hZnRlciIsInRyaWdnZXIiOiJhZG1pbi92aWV3L3NhbGUvb3JkZXJfaW5mby9hZnRlciIsImFjdGlvbiI6ImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2QvcGF5bWVudC9waW50YV9ub3ZhX3Bvc2h0YV9jb2QuYWx0ZXJPcmRlckFkZGVkQnRuIiwic3RhdHVzIjoiMSIsInNvcnRfb3JkZXIiOjk5fV0sImV4dGVuc2lvbl9jb2RlcyI6WyJQaW50YU5vdmFQb3NodGFDb2QiLCJTaW1wbGVDaGVja291dCIsImNpYWNjb3VudHNpZGViYXIiLCJkdl9kaWFsb2dpZnkiLCJkdl9vcGVuY2FydF9wYXRjaCIsImR2X3NpbXBsZV9odG1sX2RvbSIsImh1dGtvIiwibW9ub19jaGFzdCIsIm9jX2xhbmd1YWdlX2V4YW1wbGUiLCJvY19vY21vZF9leGFtcGxlIiwib2NfcGF5bWVudF9leGFtcGxlIiwib2NfdGhlbWVfZXhhbXBsZSIsIm9wZW5jYXJ0IiwicHNfZW5oYW5jZWRfbWVhc3VyZW1lbnQiLCJwc19nb29nbGVfcmVjYXB0Y2hhIiwicHNfZ29vZ2xlX3NpdGVtYXAiLCJwc19saXZlX3NlYXJjaCIsInBzX3BvcHVwX2RpYWxvZ19ib3giLCJwc19wcm9kdWN0X2NhdGVnb3J5X2ZpbHRlciIsInB1bWJfY3JlZGl0IiwidGVsZWdyYW1fbm90aWZ5IiwidWtyYWluaWFuIiwidmVudG9jYXJ0Il19fQ==';
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
