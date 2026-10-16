<?php
/** ACC-008 read-only runtime evidence. PHP 8.0+. No application/DB writes.
 * Upload inside public_html, run from public_html via CLI. The HTTPS probe
 * discloses only runtime versions/OPcache flags and never loads config/session.
 * Successful complete CLI collection self-deletes this diagnostic file only.
 */
const ACC008RE_EXPECTED_B64 = 'eyJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9jdXN0b21lci9hZGRyZXNzLnBocCI6IjJhMTcwNzQzNzhhNmZkZmI5ZmRmZTg5ZDMyNWIzZjc0MWE4MzE2Mjk4OWM0NGM4OTQwYTNlMWI1M2EzYzZmZDMiLCJhZG1pbkV2aGVuaWkvY29udHJvbGxlci9jdXN0b21lci9jdXN0b21lci5waHAiOiJjNzI1NjM4NzBlYTRmZWJhNDMwODJhZmY1ZWRhM2EyZmM2NDk3MmE3OWVjNzg0YmM1MTRhNWJhZWFjMjZhNWQ4IiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvZXZlbnQvbGFuZ3VhZ2UucGhwIjoiZWJjMDExYmUyMWFhNzhmNzY3MWRkNTQ0ODRlN2RmZGVmZDE0NDFjNGIwMzQyNGQzYTNmN2M0NWIwNWM4MjQzZiIsImFkbWluRXZoZW5paS9jb250cm9sbGVyL2V2ZW50L21vZGlmaWNhdGlvbi5waHAiOiIxN2FiYTcwZDU1N2Q1YTg2M2VmNjU1MGZkZjk3M2VkMWIyZGQ2YjBiMGY4NGJhNjc2MWY3MDE3ODNiNjUzNWQwIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9ldmVudC5waHAiOiJiOTA2ZmZlMzcyMmY1ODQ2Nzk4OGRmYTc2OGE2ZWEzZWNjNTc4M2I3YmExZmM4ZWUzMThiYjdjNTM1YWIxODAwIiwiYWRtaW5FdmhlbmlpL2NvbnRyb2xsZXIvc3RhcnR1cC9zZXNzaW9uLnBocCI6ImQ5MTM1ZjM3ZTM2MWY5MWYzYjE4MmVmY2ZmNTQ5MDBiZjI0YWM4MjQ2MzNkMTgyYWQ5M2ZlZjQ5NWVjZjliYTIiLCJhZG1pbkV2aGVuaWkvbW9kZWwvY3VzdG9tZXIvY3VzdG9tX2ZpZWxkLnBocCI6IjE5NmMzZjc2NTc1MTQ4ODMyMDNlODI1Nzg3NDUyM2EyM2QxOTVmNDc3NWRiZjMyY2RmOGU4NDdmNzM2YTU2MjciLCJhZG1pbkV2aGVuaWkvbW9kZWwvY3VzdG9tZXIvY3VzdG9tZXIucGhwIjoiOWQyYWJkMGMxYjNlZGU5YzZhZDM2MmY0MTZkMjM5N2RhMGE0ZDk3ZmY3NmZkZjE2NDI3ZGZmYzI5ZDcxNmNlYiIsImFkbWluRXZoZW5paS9tb2RlbC9kZXNpZ24vdGhlbWUucGhwIjoiN2M2MTEzZDZkOTFkYTNlMGU1YjI5ODgzODRjOWQ1ZmJiNGM3NmVlMDRhZmU0YWNhNDhhYzJhZWVhMmFiMzNhMSIsImFkbWluRXZoZW5paS9tb2RlbC9zZXR0aW5nL2V2ZW50LnBocCI6IjM3ODYxZmRhZWZhODRjODE2ZWJmMzEzYzM3ZmZiMzMzZTIzM2ViNzFhNWZhYTk4OTgwYzhjYzc5MTk1ZWNiMjQiLCJhZG1pbkV2aGVuaWkvdmlldy90ZW1wbGF0ZS9jdXN0b21lci9hZGRyZXNzX2Zvcm0udHdpZyI6IjlmZjExMTc3OTc2MzZmZDdlMWI5Nzc4ODc0MmRiOTE0ZGI1MTVjMGVjMmJiYTJlYjJkMjBjMTFlNGVlZDc1ODkiLCJhZG1pbkV2aGVuaWkvdmlldy90ZW1wbGF0ZS9jdXN0b21lci9hZGRyZXNzX2xpc3QudHdpZyI6IjVmZjkzODBlN2U3OTM2YjFkZGM3YmFmM2JmNTVlNjNhZjM0MWNkYzY2OTI2MTJmNzI5ODBmMDVhYjRlNzY5MGQiLCJhZG1pbkV2aGVuaWkvdmlldy90ZW1wbGF0ZS9jdXN0b21lci9jdXN0b21lcl9mb3JtLnR3aWciOiJmNGRjMjIxNzYxNWFlZDc5YjhjNDg0YjJmOWNkNWQ0MDU2ZmMxZTRiNDNiM2MzNTFlNjI1Y2NhMzVlNTA3NjQzIiwiY2F0YWxvZy9jb250cm9sbGVyL2FjY291bnQvYWRkcmVzcy5waHAiOiJkNjdhOTVhZWVmYWY1ZTkxODkxMmZkODA4ZDhkMGYxYzE1Yjc0NjMyYmVjOThmOGJiZjU4ZjZiZDcxZTJmZWIyIiwiY2F0YWxvZy9jb250cm9sbGVyL2FjY291bnQvZWRpdC5waHAiOiI5MmEyNTM0ODU5MDZmODVlYzQ1NjE1YzQ5NDYyZWI2MjNiMjc1MDE5Yjk1MjY4NzViZGM1ZjlkNDE2MDYyMjk5IiwiY2F0YWxvZy9jb250cm9sbGVyL2FjY291bnQvbG9naW4ucGhwIjoiNDI1YjY0MGQyMGJiZTE4NTk4NTM5YjFhMjJjYmVkMThkZWYyOWM1YzUyNWQyNThlMTdhN2ZmMjgyOWJjMmVlZSIsImNhdGFsb2cvY29udHJvbGxlci9ldmVudC9hY3Rpdml0eS5waHAiOiJmZDdiNTI5ZmU5MDVjYjE4ZjlhNjk3Y2NhMGQ5Y2IxMDZkMDVmODBlN2E0NTRlZjMyYmU4YWEwNGU2OTU4ZDY5IiwiY2F0YWxvZy9jb250cm9sbGVyL2V2ZW50L2xhbmd1YWdlLnBocCI6IjI1ZGYxZjRhNzBiNGY1MGIzNGJjNTI5MzVmZTVjNzk4MTE4OTgxYjhiMGJlMjIyOGVhYjRjODI5YTNkNDY1ZDUiLCJjYXRhbG9nL2NvbnRyb2xsZXIvZXZlbnQvbW9kaWZpY2F0aW9uLnBocCI6ImQ0NzA2ZWMxMGExNzg2Yzk1NDdmYTAyOGE1MWUyZmMzMjM3ZGU1ZDgzZGMxZDExYTE1MDFjNmM0YTQ1MWJhNWYiLCJjYXRhbG9nL2NvbnRyb2xsZXIvZXZlbnQvdGhlbWUucGhwIjoiMGVhNzg5YTQ1NTNlYTExZWM4MmUzZTU4NjFhOTY5M2U4MjZkYTY4MjcwMmFhNjNjODBhNzA2MjJkMGViMGIzMyIsImNhdGFsb2cvY29udHJvbGxlci9zdGFydHVwL2FwcGxpY2F0aW9uLnBocCI6IjkyOTVlNGE2MzkwMzEyNjA0MWZmMWEwOTUzOWI5ZjJhM2QzMjE3NjI3MmQ3Y2RmNmUyNGVmYWIwNTU4YjZiNDQiLCJjYXRhbG9nL2NvbnRyb2xsZXIvc3RhcnR1cC9jdXN0b21lci5waHAiOiIyYTA5MzZiZTJkMWJkMjI3NjEyMGMzOTQ5NGI5OWUxMjNjNjBjYzc0ZWFlYzRjMzVkZDc3NWNmM2E5Y2FkNmJlIiwiY2F0YWxvZy9jb250cm9sbGVyL3N0YXJ0dXAvZXZlbnQucGhwIjoiMzk4MGQ1ODc4OTdhMjg3MzI5ZDU1YTcxYzQyZjc3MTE2ZmU4ZTlkZmMzM2Y5N2E5MWM2MzhiODJiMzQzM2JjMSIsImNhdGFsb2cvY29udHJvbGxlci9zdGFydHVwL3Nlc3Npb24ucGhwIjoiZjQ5MzViNzQ1ZjI1YTlkM2UwMjU1NzJhMGYyMmUwYTc0MWJiMGU0MWRkZGFlYjhmYThkZTc0YzY4ZmM0OGQxOCIsImNhdGFsb2cvY29udHJvbGxlci9zdGFydHVwL3N0YXJ0dXAucGhwIjoiY2RiYWM1MDBhOTNkZWFjYjg1OWQyNzUzMTkwMjEzNTA1MDEyMjlhZmY2MWMwOTE4Y2NhOWRiMDRiYjQwMTIyYyIsImNhdGFsb2cvbW9kZWwvYWNjb3VudC9hY3Rpdml0eS5waHAiOiI4MWU5ZmYwNjE4NDdhNTBhMzNmOGFiZGY5MGE4ZmY5MTc5NjVjM2VlMTFhMTM3YWQ3ZjcxODEzMGY5NDgwZTE2IiwiY2F0YWxvZy9tb2RlbC9hY2NvdW50L2FkZHJlc3MucGhwIjoiYTEwMzkyZjk2MmFiNGQ5MmI2N2Y5Zjk3YTZkNTZlYzMzNDE5NDA0MzI0ODZmZjAxMmRkNWU2MjYwMjc3OTVmNSIsImNhdGFsb2cvbW9kZWwvYWNjb3VudC9jdXN0b21fZmllbGQucGhwIjoiYjk4M2RjMDc4ZGZkMWRkMGJiNWE3NjRiOWVjMmJiY2ZkNzAzMzYwYzUzMjg4MzkxMDBiMmI3NGJhMjU0OTI3OSIsImNhdGFsb2cvbW9kZWwvYWNjb3VudC9jdXN0b21lci5waHAiOiIyZWU2MTRmMTI2YmM4ZjEyMjcyYmE2MTJlNTA0YWQ2ODkyMjk4ZTQwZDg1NDQ0YjVlMWQwMTU1OGE5OGM5N2ZiIiwiY2F0YWxvZy9tb2RlbC9kZXNpZ24vdGhlbWUucGhwIjoiMzJiMzA2ZTE3MDBiN2U2MzY2MTdlMDQzOTIwODc2NGMwOWQxMjkxMDMyNjBmYTk2OTgwZjk3MGM1MjNmMTA0NCIsImNhdGFsb2cvbW9kZWwvc2V0dGluZy9ldmVudC5waHAiOiI4MjI0NTgxMGNiZGQ5MGNiMmNjNzY1MjU5NTc3YmZiM2MzOTlkMjRmOGFmYWMyNTczODFiZTE3ODkyMGRlNjNlIiwiY2F0YWxvZy92aWV3L3RlbXBsYXRlL2FjY291bnQvYWRkcmVzcy50d2lnIjoiZDExMmYyMWFlMzk3ZDM5MGUwNmViYjFmODI4YjNiMmUyYjFhODM4ZWYyOTMxZGY4ZWQxYWE0MzU0MTU0MjdjMiIsImNhdGFsb2cvdmlldy90ZW1wbGF0ZS9hY2NvdW50L2FkZHJlc3NfZm9ybS50d2lnIjoiNzMwYTA3MzEzMjFiY2YzOWRkYWIwYjhmNDRiOTA2MzY0NjJhMmM1OWJlNjYyNWRhN2VjMDBmNTA1NDQzODA3ZSIsImNhdGFsb2cvdmlldy90ZW1wbGF0ZS9hY2NvdW50L2FkZHJlc3NfbGlzdC50d2lnIjoiOGQ4ZTViNDU0YWNlNjZiMmI1MTU4MjU5OTdlYWFkZTYwYWQwYzJhODk5Y2JlMGM3NjA3YTgxMWZiMmNiZjIwZSIsImNhdGFsb2cvdmlldy90ZW1wbGF0ZS9hY2NvdW50L2VkaXQudHdpZyI6IjI4NDhhYzU3YWU0OWVhYjVjOGYwMzVhYzM3MTMwMjU3MTE3ZmFhODNkYTlkNmU0NjFmN2I0MzgwMmUyNTY4NmIiLCJleHRlbnNpb24vUGludGFOb3ZhUG9zaHRhQ29kL2NhdGFsb2cvbW9kZWwvbW9kdWxlL2FyZWEucGhwIjoiYTJmNjJjOTdlMGI3NGUyOTRmYzYwZTFmNjU5N2ZlMjg5NDBhYzM5YWQ4M2JmZDc5MWE4MjM5ZDIwM2U1OTVmMiIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2QvY2F0YWxvZy9tb2RlbC9tb2R1bGUvY2l0eS5waHAiOiJjYjdjZDQ2Zjk4NDQyOTJiZGUzYjk4OTE4NDAzOWFhODk3MTEwNjdhZTdlMDkxZTJiZjEwMTAwY2M2YjZhNDQ4IiwiZXh0ZW5zaW9uL1BpbnRhTm92YVBvc2h0YUNvZC9jYXRhbG9nL21vZGVsL21vZHVsZS9zdHJlZXQucGhwIjoiYTkxZTcxZjVlMzI2MDFhNDBmMzQwY2VhZmNhYjE4NDU2YTE2NjhiMDljYThhZmQ5Y2ViZTA5ZDk2YTNkMTcyNiIsImV4dGVuc2lvbi9QaW50YU5vdmFQb3NodGFDb2QvY2F0YWxvZy9tb2RlbC9tb2R1bGUvd2FyZWhvdXNlLnBocCI6ImQwOGYyOWVjNzhmNjViOWY4OGRlYmY4YzQ0YWY4MDE0ZWZkZDViM2Y3OWRlOWU2ZDRiZTgxYTViMWJjMzcxYjYiLCJleHRlbnNpb24vb3BlbmNhcnQvY2F0YWxvZy9tb2RlbC90b3RhbC9jb3Vwb24ucGhwIjoiYjliZTFkODY2N2NmNzhlNTc2YTg2NWI5ZWM4MmQ5M2FiZjQwYmY5Yzg4ZjkwM2M4MTk0YmZlYjhlMGVjNTBjNCIsImV4dGVuc2lvbi91a3JhaW5pYW4vYWRtaW4vbGFuZ3VhZ2UvdWstdWEvY3VzdG9tZXIvY3VzdG9tZXIucGhwIjoiZTA2YTlhNjcyZWU0NDQ4NDU0OTg1NmRhN2MzY2VhYWNlODc5NWQyNzk1MDU2NzVlN2UzOGNjZGE2ODRhMDljZCIsImV4dGVuc2lvbi91a3JhaW5pYW4vY2F0YWxvZy9sYW5ndWFnZS91ay11YS9hY2NvdW50L2FkZHJlc3MucGhwIjoiYTNhMWE4NTI2OWYyN2E3NTVkNjI5ZTZlM2RlMDIyNGU2MDBmNmRhOWViNDhkM2I5ZTc5YjUxNzNjMjVjNWNmMiIsImV4dGVuc2lvbi91a3JhaW5pYW4vY2F0YWxvZy9sYW5ndWFnZS91ay11YS9hY2NvdW50L2VkaXQucGhwIjoiNzEwZTQxNzAwNDEwZGJkOGNiMTRhMjk4NWMzZDJmODVkZWIxMDY0NzhhNTNhNzI3MzdjNWMzOWZlNjZjNmQ3OSIsInN5c3RlbS9jb25maWcvYWRtaW4ucGhwIjoiMWE5ODZkZjhjYjVmNWJhMjJkODJhYWRiM2ZjNGQwNTk2MGFiYzIwYzZhMTA5NTQ4NmM0YTMwYzYxMzMxZjlhZiIsInN5c3RlbS9jb25maWcvY2F0YWxvZy5waHAiOiJmZGM1NzEzZmE4ZTlkNzMyYTQxODM0ODU5MzBiZjRmMjkxMDRlYThjMWQ0ZjA1OTQ0N2EzMDBjMDMzYzgwNDQzIiwic3lzdGVtL2NvbmZpZy9kZWZhdWx0LnBocCI6ImM1NmQ0MWQwODMzNmFhYzZkNjUyYTNiNWVjZDg3YTExYmUwNTMzNGEyMmI0ZmY5MDE3ZmEwM2RiZWFmNzA4NzAiLCJzeXN0ZW0vZW5naW5lL2FjdGlvbi5waHAiOiI5MWE0YWYzNzc1ZTljMWM1OTlmYWY1NWYyYjFmOWVhYzg5Yjg5ZDE3ODY1N2M2YmQ4ZWM3YjYxMDU0MzczZTc5Iiwic3lzdGVtL2VuZ2luZS9hdXRvbG9hZGVyLnBocCI6ImMzMTBmMWNhYzUzMTgwYjMwMWRkMDQ4ZjAxNzVhMzU5YmVlMWNjZTcwZjRjOTQyODUxYjhhZDMwNTM5Zjk5YjMiLCJzeXN0ZW0vZW5naW5lL2NvbmZpZy5waHAiOiJkMWE3MjQwN2M3N2Y2ZGRjNDQ4Y2NhZTkwZDQ4OWNkNTkwN2U1Y2I0MTA4ZTdkZWNjYTRiNDZlYjM1NDFiZDBkIiwic3lzdGVtL2VuZ2luZS9jb250cm9sbGVyLnBocCI6IjhhNTFlZjExMjRkYTNiOWI2YWU5MWIyZWM5MTRkZjFmMmJiZTk1MTQyYjc1MzQzMWY3Y2ZhMGU3NTBjYmUwM2IiLCJzeXN0ZW0vZW5naW5lL2V2ZW50LnBocCI6IjdjMTMyYTRkZDE3NDkyMTQ2MWI0Nzc0NjQ3YWVlN2Y0YjM5NzRjNTNmYWJlODRlNmEwY2NkNzRiMGQ3Mzc1MjQiLCJzeXN0ZW0vZW5naW5lL2ZhY3RvcnkucGhwIjoiZmQ4YTg5NDA5MTVkOWFlM2RiMmMwYmNkNWNlYmUyMjU0NGY2NTMyNTcyNjQ3ODAwNjZlM2E1YTJhZTdjZjdmZSIsInN5c3RlbS9lbmdpbmUvbG9hZGVyLnBocCI6ImVhMjA5ODQ1MGRkYzU2Nzc0N2UyZDg5MDAxZDk5N2NlOTIxZTk2NmEzMTNjYWZiNjgwYTMwOGVlMzY5YTQ3MjMiLCJzeXN0ZW0vZW5naW5lL21vZGVsLnBocCI6ImNkM2UwNDI2YmY4MGMwMGU2NTRiYTUxOGZhNGMwYjNlODQ2ODY2NmMzOWY4NjAxMWUxMTAyOWVjYmUyOGQ5NTAiLCJzeXN0ZW0vZW5naW5lL3Byb3h5LnBocCI6IjU2OWFmOGI1MDkwOWJlMjVkY2QzN2Y2ZDhhZmMzNDc4YWRhMDhhZjNkNTlkM2VjNWQxNjYxODc2NjJjYTQ0ZDkiLCJzeXN0ZW0vZW5naW5lL3JlZ2lzdHJ5LnBocCI6IjMyNWM2ZTBmNjdmZGZkYTIwOTI2NzA0ODViM2FjMzhmY2M5MTE2Nzg5MGMyNTMxNjA5YWNhZWI4NzM5MzM2MzciLCJzeXN0ZW0vZnJhbWV3b3JrLnBocCI6IjdiZDIxZjliMmQ5MTY3YjNmNzBjMTg5OTY4N2MxODdkNzY5MDIyNDQzZDIwOTFhM2RlOTMwYTQxYzE0YjUwODgiLCJzeXN0ZW0vaGVscGVyL3V0ZjgucGhwIjoiNzJmZThlMjQ4ZTBjYzM3Y2Y4NjkwOGNkYTc2MzMxOGEwN2RhZjNiOTYzMTQ1ZDg0OTM3YTEwNmY4ZWQzNTBlNSIsInN5c3RlbS9oZWxwZXIvdmFsaWRhdGlvbi5waHAiOiIxMWJjOTJlM2M0MDgzNzIzNzY2MTQwMGU5YzdmNTdiNmFmODZkYjk4Yzk1MTY4MTViY2Q3YzM3YzQ0N2U1N2IyIiwic3lzdGVtL2xpYnJhcnkvY2FydC9jdXN0b21lci5waHAiOiI0ZmE0YzJkMWIxOTM5YTY5MzEyZWUwYTUzYjA2ZTFhOGE5M2ZjMTY3MWNiYTEwM2FlZmU3YzU3NTZiNDdkMTczIiwic3lzdGVtL2xpYnJhcnkvZGIvbXlzcWxpLnBocCI6ImY0N2UxOGM5ZmZhMmIzNDBmZGQ5ZGY4YzVkNWMwNGViNGY4OTlhNmQ0NjEwYTQzMmU1Y2ZkMmViNjVmOGU3MmYiLCJzeXN0ZW0vbGlicmFyeS9yZXF1ZXN0LnBocCI6IjQ4M2MxYTQ0ZWMwYzdjOWQ2OTY5NTcwZDc2NmMxY2Q4YzQwZTc2NDZkMTlkYTBmN2ExMWQwZGRhNzk3OWY1MmMiLCJzeXN0ZW0vbGlicmFyeS9zZXNzaW9uLnBocCI6IjUxODE1ZjJmYjhkMzIxY2M3ZWNkNzI2ZmE0N2FiZDQxZTdlZWQ5NTVlMjg1YTg4MGU0YjA0ZjcyZDZlZjY5MmIiLCJzeXN0ZW0vbGlicmFyeS9zZXNzaW9uL2RiLnBocCI6ImJkZjZjMTdhMGVkNGJmNWRlMGFjMTZhNmEzNWU3MTM4NThhYTkyMTY1ZjM5YmZlNDYzMTMzODRmNGQ0MDI5OTUiLCJzeXN0ZW0vbGlicmFyeS9zZXNzaW9uL2ZpbGUucGhwIjoiNzczNGUxYjM4YTYwZGQ4YjJiYTcwMTlhMTQwNzMwYTk4YjQyOWVhMjhlNzUzYmVmNmU3MThmZjJhOTAwMTcwNSIsInN5c3RlbS9saWJyYXJ5L3RlbXBsYXRlLnBocCI6IjdmZGU2Yjk1NjlhYTg3OTRmMTRkZWU4YzhjMDY3NzU5MTQ1MzM0MmRlZmUwODgyYzIwOGQ0YjY4OTZiMzA2ZDAiLCJzeXN0ZW0vbGlicmFyeS90ZW1wbGF0ZS90d2lnLnBocCI6IjkyYTZhYzBhZjZmZjg4MDUzZWNhZmUwYWNkODZlMjQ5YmRkYWFiNjY0MDgwYTU0OWMwYTUwMjE3OWY1MzdhNjkifQ==';
class Acc008RuntimeEvidenceException extends RuntimeException {}
function acc008re_fail(string $code): void { throw new Acc008RuntimeEvidenceException($code); }
function acc008re_runtime(): array {
    return ['php_version'=>PHP_VERSION,'sapi'=>PHP_SAPI,
        'mysqli_client'=>function_exists('mysqli_get_client_info') ? mysqli_get_client_info() : null,
        'opcache'=>['enabled'=>ini_get('opcache.enable')==='1','validate_timestamps'=>ini_get('opcache.validate_timestamps')==='1','revalidate_freq'=>(int)ini_get('opcache.revalidate_freq')]];
}
function acc008re_literal(array $token): string {
    if ($token[0] === T_LNUMBER && preg_match('/^[0-9]+$/D',$token[1])) return $token[1];
    if ($token[0] !== T_CONSTANT_ENCAPSED_STRING) acc008re_fail('admin_target_requires_literal_constants');
    $value=substr($token[1],1,-1);
    if (!preg_match('~^[A-Za-z0-9_.:/\[\]-]*$~D',$value)) acc008re_fail('admin_target_literal_format_requires_review');
    return $value;
}
/** Parse only target identity constants; never execute admin config or return credentials. */
function acc008re_admin_target(string $code): array {
    $tokens=array_values(array_filter(token_get_all($code),fn($t)=>!is_array($t)||!in_array($t[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)));
    $allowed=['DB_HOSTNAME','DB_PORT','DB_DATABASE','DB_PREFIX','DB_DRIVER'];$values=[];
    for($i=0;$i<count($tokens);$i++) {
        if(is_array($tokens[$i])&&in_array($tokens[$i][0],[T_IF,T_ELSE,T_ELSEIF,T_SWITCH,T_FOR,T_FOREACH,T_WHILE,T_DO,T_FUNCTION,T_CLASS,T_NAMESPACE,T_INCLUDE,T_INCLUDE_ONCE,T_REQUIRE,T_REQUIRE_ONCE,T_EVAL],true))acc008re_fail('conditional_or_indirect_admin_config_requires_review');
        if(!is_array($tokens[$i])||$tokens[$i][0]!==T_STRING||strtolower($tokens[$i][1])!=='define')continue;
        $prior=$tokens[$i-1]??null;$prior=is_array($prior)?$prior[1]:$prior;
        if(in_array($prior,['->','?->','::'],true))acc008re_fail('indirect_admin_definition_requires_review');
        if(($tokens[$i+1]??null)!=='('||!is_array($tokens[$i+2]??null)||$tokens[$i+2][0]!==T_CONSTANT_ENCAPSED_STRING)continue;
        $name=substr($tokens[$i+2][1],1,-1);if(!in_array($name,$allowed,true))continue;
        if(isset($values[$name])||($tokens[$i+3]??null)!==','||!is_array($tokens[$i+4]??null)||($tokens[$i+5]??null)!==')')acc008re_fail('admin_target_definition_requires_review');
        $values[$name]=acc008re_literal($tokens[$i+4]);
    }
    foreach($allowed as$name)if(!array_key_exists($name,$values))acc008re_fail('admin_target_definition_missing');
    if(!preg_match('/^[0-9]+$/D',$values['DB_PORT'])||(int)$values['DB_PORT']<1||(int)$values['DB_PORT']>65535)acc008re_fail('admin_target_port_requires_review');
    return $values;
}
function acc008re_target_flags(array $admin,array $catalog): array {
    return ['catalog_persistent'=>stripos($catalog['DB_HOSTNAME'],'p:')===0,'admin_persistent'=>stripos($admin['DB_HOSTNAME'],'p:')===0,
        'same_host'=>$admin['DB_HOSTNAME']===$catalog['DB_HOSTNAME'],'same_port'=>(int)$admin['DB_PORT']===(int)$catalog['DB_PORT'],
        'same_database'=>$admin['DB_DATABASE']===$catalog['DB_DATABASE'],'same_prefix'=>$admin['DB_PREFIX']===$catalog['DB_PREFIX'],
        'same_driver'=>$admin['DB_DRIVER']===$catalog['DB_DRIVER']];
}
function acc008re_path(string $root,string $relative): string {
    if(!preg_match('~^[A-Za-z0-9_./-]+$~D',$relative)||strpos($relative,'..')!==false||$relative[0]==='/')acc008re_fail('unsafe_evidence_path');
    $path=$root;foreach(explode('/',$relative)as$part){$path.='/'.$part;if(is_link($path))acc008re_fail('linked_evidence_path');}return $path;
}
function acc008re_sources(string $root,array $expected): array {
    if(count($expected)>256)acc008re_fail('source_identity_cap');$rows=[];
    foreach($expected as$relative=>$sha){
        $path=acc008re_path($root,$relative);
        if(is_file($path)&&filesize($path)>2000000)acc008re_fail('source_file_size_cap');
        $actual=is_file($path)?hash_file('sha256',$path):null;
        $rows[]=['path'=>$relative,'exists'=>is_file($path),'expected_sha256'=>$sha,'actual_sha256'=>$actual,'matches'=>$actual===$sha];
    }return $rows;
}
function acc008re_ocmod(string $root,array $sourcePaths): array {
    $rows=[];
    foreach($sourcePaths as$relative){
        if(strpos($relative,'catalog/')===0)$candidate='extension/ocmod/'.$relative;
        elseif(strpos($relative,'adminEvhenii/')===0)$candidate='extension/ocmod/admin/'.substr($relative,strlen('adminEvhenii/'));
        elseif(strpos($relative,'extension/')===0)$candidate='extension/ocmod/'.$relative;
        elseif(strpos($relative,'system/library/')===0)$candidate='extension/ocmod/'.$relative;
        else continue;
        $path=acc008re_path($root,$candidate);$exists=is_file($path);
        $rows[]=['candidate_path'=>$candidate,'present'=>$exists,'sha256'=>$exists?hash_file('sha256',$path):null];
    }return $rows;
}
function acc008re_rows(mysqli $db, string $sql, int $cap = 128): array {
    $statement = $db->prepare($sql);
    if (!$statement) acc008re_fail('read_only_metadata_query_failed');
    try {
        if (!$statement->execute()) acc008re_fail('read_only_metadata_query_failed');
        $meta = $statement->result_metadata();
        if (!$meta) acc008re_fail('metadata_result_missing');
        $bound = []; $refs = [];
        foreach ($meta->fetch_fields() as $column) { $bound[$column->name] = null; $refs[] = &$bound[$column->name]; }
        $meta->free();
        if (!$statement->bind_result(...$refs)) acc008re_fail('metadata_bind_failed');
        $rows = [];
        while (($fetch = $statement->fetch()) === true) {
            $row = []; foreach ($bound as $key => $value) $row[$key] = $value;
            $rows[] = $row;
            if (count($rows) > $cap) acc008re_fail('metadata_result_cap');
        }
        if ($fetch === false || $statement->errno) acc008re_fail('metadata_fetch_failed');
        return $rows;
    } finally {
        $statement->close();
    }
}
function acc008re_collect(string $root,array $expected): array {
    $config=acc008re_path($root,'config.php');$adminConfig=acc008re_path($root,'adminEvhenii/config.php');
    if(!is_file($config)||!is_file($adminConfig)||filesize($adminConfig)>524288)acc008re_fail('hosting_config_missing_or_oversize');
    // Root config is the same owner-host read required by the accepted dry-run.
    // Trap all output; never print its contents or exception messages.
    ob_start();try{require $config;}finally{$configOutput=ob_get_clean();}
    if($configOutput!=='')acc008re_fail('catalog_config_unexpected_output');
    foreach(['DB_HOSTNAME','DB_USERNAME','DB_PASSWORD','DB_DATABASE','DB_PREFIX','DB_PORT','DB_DRIVER']as$key)if(!defined($key))acc008re_fail('catalog_db_config_incomplete');
    $catalog=[];foreach(['DB_HOSTNAME','DB_PORT','DB_DATABASE','DB_PREFIX','DB_DRIVER']as$key)$catalog[$key]=(string)constant($key);
    $admin=acc008re_admin_target(file_get_contents($adminConfig));$flags=acc008re_target_flags($admin,$catalog);unset($admin,$catalog);
    if(!preg_match('/^[A-Za-z0-9_]+$/D',DB_PREFIX)||!class_exists('mysqli'))acc008re_fail('mysqli_or_prefix_invalid');
    if($flags['catalog_persistent'])acc008re_fail('persistent_catalog_connection_not_opened');
    mysqli_report(MYSQLI_REPORT_OFF);
    $db=@new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE,(int)DB_PORT);
    if($db->connect_errno||!$db->set_charset('utf8mb4'))acc008re_fail('metadata_connection_failed');
    try{
        $engines=[];
        foreach(['customer','address','customer_activity','session','customer_login']as$table){
            $name=$db->real_escape_string(DB_PREFIX.$table);
            $rows=acc008re_rows($db,"SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$name."'",1);
            $engines[]=['table'=>$table,'exists'=>count($rows)===1,'engine'=>$rows[0]['engine']??null];
        }
        $settings=acc008re_rows($db,'SELECT store_id, value FROM `'.DB_PREFIX.'setting` WHERE `key` = \'config_customer_activity\' AND code = \'config\' ORDER BY store_id LIMIT 33',32);
        $activity=[];foreach($settings as$row){$value=(string)$row['value'];$valid=in_array($value,['0','1','false','true',''],true);$activity[]=['store_id'=>(int)$row['store_id'],'format_valid'=>$valid,'enabled'=>$valid?in_array($value,['1','true'],true):null];}
        $server=$db->server_info;
    }finally{$db->close();}
    return ['task'=>'ACC-008-runtime-evidence','cli'=>acc008re_runtime(),'db_server_version'=>$server,'db_target_flags'=>$flags,
        'table_engines'=>$engines,'customer_activity_settings'=>$activity,'source_identities'=>acc008re_sources($root,$expected),
        'extension_root_matches_public_extension'=>defined('DIR_EXTENSION')&&realpath(DIR_EXTENSION)===realpath($root.'/extension'),
        'ocmod_candidate_files'=>acc008re_ocmod($root,array_keys($expected)),
        'outside_webroot_backup_parent_writable'=>is_writable(dirname($root)),
        'limits'=>'No customer/order/session rows read. OCMOD candidate file checks are evidence, not universal override admission. No application/DB/cache writes.'];
}
function acc008re_validate_probe(string $body,string $nonce,int $status,string $contentType): array {
    if($status!==200||stripos($contentType,'application/json')!==0||strlen($body)>16384)acc008re_fail('web_probe_protocol_rejected');
    try{$data=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(JsonException$e){acc008re_fail('web_probe_json_rejected');}
    if(!is_array($data)||array_keys($data)!==['task','probe_nonce','runtime']||$data['task']!=='ACC-008-public-runtime'||$data['probe_nonce']!==$nonce||!is_array($data['runtime']))acc008re_fail('web_probe_identity_rejected');
    $r=$data['runtime'];if(array_keys($r)!==['php_version','sapi','mysqli_client','opcache']||!is_string($r['php_version'])||!is_string($r['sapi'])||$r['sapi']==='cli'||!(is_string($r['mysqli_client'])||$r['mysqli_client']===null)||!is_array($r['opcache']))acc008re_fail('web_probe_runtime_rejected');
    if(array_keys($r['opcache'])!==['enabled','validate_timestamps','revalidate_freq']||!is_bool($r['opcache']['enabled'])||!is_bool($r['opcache']['validate_timestamps'])||!is_int($r['opcache']['revalidate_freq']))acc008re_fail('web_probe_opcache_rejected');
    return $r;
}
function acc008re_probe(): array {
    if(!function_exists('curl_init'))return ['status'=>'unavailable','reason'=>'curl_extension_missing'];
    $nonce=bin2hex(random_bytes(16));$body='';$url='https://boostershop.website/'.rawurlencode(basename(__FILE__)).'?acc008_runtime_probe='.$nonce;
    $curl=curl_init($url);if($curl===false)return ['status'=>'unavailable','reason'=>'curl_init_failed'];
    try{
        curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Accept: application/json','Cache-Control: no-cache'],
            CURLOPT_WRITEFUNCTION=>static function($handle,$part)use(&$body){if(strlen($body)+strlen($part)>16384)return 0;$body.=$part;return strlen($part);}]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);$type=(string)curl_getinfo($curl,CURLINFO_CONTENT_TYPE);
        if($ok===false)return ['status'=>'unavailable','reason'=>'https_probe_failed','curl_error_number'=>curl_errno($curl)];
        try{return ['status'=>'verified','runtime'=>acc008re_validate_probe($body,$nonce,$status,$type)];}catch(Acc008RuntimeEvidenceException$e){return ['status'=>'unavailable','reason'=>$e->getMessage()];}
    }finally{curl_close($curl);}
}
// ACC008RE_ENTRY: test harness extracts complete functions before this entry.
if(PHP_SAPI!=='cli'){
    $nonce=$_GET['acc008_runtime_probe']??null;
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET'||!is_string($nonce)||!preg_match('/^[a-f0-9]{32}$/D',$nonce)){http_response_code(403);exit;}
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, max-age=0');header('X-Content-Type-Options: nosniff');
    echo json_encode(['task'=>'ACC-008-public-runtime','probe_nonce'=>$nonce,'runtime'=>acc008re_runtime()],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
}
try{
    if(PHP_VERSION_ID<80000)acc008re_fail('php_8_cli_required');
    if(count($argv)!==1)acc008re_fail('no_arguments_supported');
    $root=realpath(getcwd());if(!$root||realpath(__FILE__)!==$root.DIRECTORY_SEPARATOR.basename(__FILE__)||is_link(__FILE__))acc008re_fail('run_inside_public_html_with_uploaded_diagnostic');
    $expected=json_decode(base64_decode(ACC008RE_EXPECTED_B64,true),true,512,JSON_THROW_ON_ERROR);
    $metadata=acc008re_collect($root,$expected);$metadata['web_probe']=acc008re_probe();
    echo 'task=ACC-008-runtime-evidence'."\n".'time='.gmdate('c')."\n".'runtime_metadata='.json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    if($metadata['web_probe']['status']!=='verified'){echo "done=partial\nrunner_kept=yes\n";exit(1);}
    if(!unlink(__FILE__))acc008re_fail('diagnostic_self_delete_failed');
    echo "self_deleted=yes\ndone=ok\n";
}catch(Acc008RuntimeEvidenceException$e){fwrite(STDERR,'error='.$e->getMessage()."\n");exit(1);}
catch(Throwable$e){fwrite(STDERR,"error=unexpected_runtime_evidence_failure\n");exit(1);}
