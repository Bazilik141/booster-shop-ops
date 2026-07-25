<?php
declare(strict_types=1);

/*
 * PAY-001 Phase 2c D3 — let the latest product-modal credit term replace an earlier saved Mono credit selection.
 * No DB/settings/order-write changes. Rollback: restore files from the printed backup directory.
 */

$patch_id = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();
$manifest = json_decode(base64_decode('eyJjYXRhbG9nL2NvbnRyb2xsZXIvY2hlY2tvdXQvY2hlY2tvdXQucGhwIjp7Im9sZF9zaGEyNTYiOiI4Y2Y4MDhkMDg4MzE5NjhkMzEyMTk5MmY0Y2FhYjA1MmVlODU3MDg0NmVlYzA2MWQ3YzhjMjU1OTcwYjkyNDE2IiwibmV3X3NoYTI1NiI6ImZkNTk0M2QyMGJiZTQzN2U2NmFmN2EzYjk4OTNjMjEzNGVkZThkMjEyNTY0ZWIzMTgwMjRjOTQ3YzI5ZTViNjAiLCJhbmNob3IiOiIvLyBQQVktMDAxLVBIQVNFMi1DUkVESVQtVUktMjAyNjA3MjE6IHByb2R1Y3QgVUkgY2FuIHN1Z2dlc3Qgb25seSAzLzQvNS4iLCJhbmNob3JfY291bnQiOjEsInBocF9sIjp0cnVlLCJjb250ZW50X2Jhc2U2NCI6IlBEOXdhSEFLYm1GdFpYTndZV05sSUU5d1pXNWpZWEowWEVOaGRHRnNiMmRjUTI5dWRISnZiR3hsY2x4RGFHVmphMjkxZERzS0x5b3FDaUFxSUVOc1lYTnpJRU5vWldOcmIzVjBDaUFxQ2lBcUlFQndZV05yWVdkbElFOXdaVzVqWVhKMFhFTmhkR0ZzYjJkY1EyOXVkSEp2Ykd4bGNseERhR1ZqYTI5MWRBb2dLaThLWTJ4aGMzTWdRMmhsWTJ0dmRYUWdaWGgwWlc1a2N5QmNUM0JsYm1OaGNuUmNVM2x6ZEdWdFhFVnVaMmx1WlZ4RGIyNTBjbTlzYkdWeUlIc0tDUzhxS2dvSklDb2dTVzVrWlhnS0NTQXFDZ2tnS2lCQWNtVjBkWEp1SUhadmFXUUtDU0FxTHdvSmNIVmliR2xqSUdaMWJtTjBhVzl1SUdsdVpHVjRLQ2s2SUhadmFXUWdld29KQ1FrSkx5OGdWbUZzYVdSaGRHVWdZMkZ5ZENCMGJ5QnpaV1VnYVdZZ2FYUWdhR0Z6SUhCeWIyUjFZM1J6SUdGdVpDQm9ZWE1nYzNSdlkyc3VDZ2tKSkhCeVpXOXlaR1Z5WDNOMGIyTnJYM04wWVhSMWMxOXBaQ0E5SURnN0Nna0pKR2hoYzE5emRHOWphMTlsY25KdmNpQTlJR1poYkhObE93b0tDUWxtYjNKbFlXTm9JQ2drZEdocGN5MCtZMkZ5ZEMwK1oyVjBVSEp2WkhWamRITW9LU0JoY3lBa1kyRnlkRjl3Y205a2RXTjBLU0I3Q2drSkNTUnBjMTl3Y21WdmNtUmxjaUE5SUdsemMyVjBLQ1JqWVhKMFgzQnliMlIxWTNSYkozTjBiMk5yWDNOMFlYUjFjMTlwWkNkZEtTQW1KaUFvYVc1MEtTUmpZWEowWDNCeWIyUjFZM1JiSjNOMGIyTnJYM04wWVhSMWMxOXBaQ2RkSUQwOVBTQWtjSEpsYjNKa1pYSmZjM1J2WTJ0ZmMzUmhkSFZ6WDJsa093b0tDUWtKYVdZZ0tDRW9hVzUwS1NSallYSjBYM0J5YjJSMVkzUmJKM04wYjJOckoxMGdKaVlnSVNScGMxOXdjbVZ2Y21SbGNpa2dld29KQ1FrSkpHaGhjMTl6ZEc5amExOWxjbkp2Y2lBOUlIUnlkV1U3Q2drSkNRbGljbVZoYXpzS0NRa0pmUW9KQ1gwS0Nna0phV1lnS0FvSkNRa2hKSFJvYVhNdFBtTmhjblF0UG1oaGMxQnliMlIxWTNSektDa2dmSHdLQ1FrSktDUm9ZWE5mYzNSdlkydGZaWEp5YjNJZ0ppWWdJU1IwYUdsekxUNWpiMjVtYVdjdFBtZGxkQ2duWTI5dVptbG5YM04wYjJOclgyTm9aV05yYjNWMEp5a3BJSHg4Q2drSkNTRWtkR2hwY3kwK1kyRnlkQzArYUdGelRXbHVhVzExYlNncENna0pLU0I3Q2drSkNTUjBhR2x6TFQ1eVpYTndiMjV6WlMwK2NtVmthWEpsWTNRb0pIUm9hWE10UG5WeWJDMCtiR2x1YXlnblkyaGxZMnR2ZFhRdlkyRnlkQ2NzSUNkc1lXNW5kV0ZuWlQwbklDNGdKSFJvYVhNdFBtTnZibVpwWnkwK1oyVjBLQ2RqYjI1bWFXZGZiR0Z1WjNWaFoyVW5LU3dnZEhKMVpTa3BPd29KQ1gwS0Nnb0pDU1IwYUdsekxUNXNiMkZrTFQ1c1lXNW5kV0ZuWlNnblkyaGxZMnR2ZFhRdlkyaGxZMnR2ZFhRbktUc0tDZ2tKTHk4Z1VFRlpMVEF3TVMxUVNFRlRSVEl0UTFKRlJFbFVMVlZKTFRJd01qWXdOekl4T2lCd2NtOWtkV04wSUZWSklHTmhiaUJ6ZFdkblpYTjBJRzl1YkhrZ015ODBMelV1Q2drSkx5OGdWR2hsSUhOMGIyTnJJSEJoZVcxbGJuUWdZMjl1ZEhKdmJHeGxjaUIyWVd4cFpHRjBaWE1nZEdobElHWnBibUZzSUhacGNuUjFZV3dnYjNCMGFXOXVJR0ZuWVdsdUxnb0pDU1J3WVhrd01ERmZjR0Z5ZEhNZ1BTQnBjM05sZENna2RHaHBjeTArY21WeGRXVnpkQzArWjJWMFd5ZHRiMjV2WDJOb1lYTjBYM0JoY25SekoxMHBJRDhnS0dsdWRDa2tkR2hwY3kwK2NtVnhkV1Z6ZEMwK1oyVjBXeWR0YjI1dlgyTm9ZWE4wWDNCaGNuUnpKMTBnT2lBd093b0pDV2xtSUNocGJsOWhjbkpoZVNna2NHRjVNREF4WDNCaGNuUnpMQ0JiTXl3Z05Dd2dOVjBzSUhSeWRXVXBLU0I3Q2drSkNTOHZJRkJCV1Mwd01ERXRVRWhCVTBVeVF5MUVNeTFEVWtWRVNWUXRWRVZTVFMweU1ESTJNRGN5TlRvS0NRa0pMeThnWVNCbWNtVnphQ0J0YjJSaGJDQnlaV1JwY21WamRDQnBjeUJoZFhSb2IzSnBkR0YwYVhabElHOTJaWElnWVNCamNtVmthWFFnYldWMGFHOWtJSE5oZG1Wa0Nna0pDUzh2SUdKNUlHRnVJR1ZoY214cFpYSWdjSEp2WkhWamRDNGdRMnhsWVhJZ2IyNXNlU0IwYUdGMElITjBZV3hsSUdOeVpXUnBkQ0J6Wld4bFkzUnBiMjRnYzI4S0NRa0pMeThnY0dGNWJXVnVkRjl0WlhSb2IyUXVkSGRwWnlCallXNGdZWEJ3YkhrZ1lXNWtJSE5oZG1VZ2RHaGxJRzVsZDJ4NUlISmxjWFZsYzNSbFpDQjBaWEp0TGdvSkNRa2tZM1Z5Y21WdWRGOXdZWGt3TURGZlkyOWtaU0E5SUNoemRISnBibWNwS0NSMGFHbHpMVDV6WlhOemFXOXVMVDVrWVhSaFd5ZHdZWGx0Wlc1MFgyMWxkR2h2WkNkZFd5ZGpiMlJsSjEwZ1B6OGdKeWNwT3dvSkNRbHBaaUFvYzNSeVgzTjBZWEowYzE5M2FYUm9LQ1JqZFhKeVpXNTBYM0JoZVRBd01WOWpiMlJsTENBbmJXOXViMTlqYUdGemRDNG5LU2tnZXdvSkNRa0pkVzV6WlhRb0pIUm9hWE10UG5ObGMzTnBiMjR0UG1SaGRHRmJKM0JoZVcxbGJuUmZiV1YwYUc5a0oxMHBPd29KQ1FsOUNna0pDU1IwYUdsekxUNXpaWE56YVc5dUxUNWtZWFJoV3lkd1lYa3dNREZmYlc5dWIxOWphR0Z6ZEY5d1lYSjBjeWRkSUQwZ0pIQmhlVEF3TVY5d1lYSjBjenNLQ1FrSkpIUm9hWE10UG5ObGMzTnBiMjR0UG1SaGRHRmJKM0JoZVRBd01WOXRiMjV2WDJOb1lYTjBYMlp5YjIxZmJXOWtZV3duWFNBOUlERTdDZ2tKZlNCbGJITmxJSHNLQ1FrSkx5OGdSR2x5WldOMElHOXlJRzFoYkdadmNtMWxaQ0JqYUdWamEyOTFkQ0JWVWt4eklHMTFjM1FnYm1WMlpYSWdhVzVvWlhKcGRDQmhJR055WldScGRDMXRiMlJoYkNCamFHOXBZMlV1Q2drSkNYVnVjMlYwS0NSMGFHbHpMVDV6WlhOemFXOXVMVDVrWVhSaFd5ZHdZWGt3TURGZmJXOXViMTlqYUdGemRGOXdZWEowY3lkZExDQWtkR2hwY3kwK2MyVnpjMmx2YmkwK1pHRjBZVnNuY0dGNU1EQXhYMjF2Ym05ZlkyaGhjM1JmWm5KdmJWOXRiMlJoYkNkZEtUc0tDUWw5Q2drSkpIUm9hWE10UG1SdlkzVnRaVzUwTFQ1elpYUlVhWFJzWlNna2RHaHBjeTArYkdGdVozVmhaMlV0UG1kbGRDZ25hR1ZoWkdsdVoxOTBhWFJzWlNjcEtUc0tDZ2tKSkdSaGRHRmJKMkp5WldGa1kzSjFiV0p6SjEwZ1BTQmJYVHNLQ2drSkpHUmhkR0ZiSjJKeVpXRmtZM0oxYldKekoxMWJYU0E5SUZzS0NRa0pKM1JsZUhRbklEMCtJQ1IwYUdsekxUNXNZVzVuZFdGblpTMCtaMlYwS0NkMFpYaDBYMmh2YldVbktTd0tDUWtKSjJoeVpXWW5JRDArSUNSMGFHbHpMVDUxY213dFBteHBibXNvSjJOdmJXMXZiaTlvYjIxbEp5d2dKMnhoYm1kMVlXZGxQU2NnTGlBa2RHaHBjeTArWTI5dVptbG5MVDVuWlhRb0oyTnZibVpwWjE5c1lXNW5kV0ZuWlNjcEtRb0pDVjA3Q2dvSkNTUmtZWFJoV3lkaWNtVmhaR055ZFcxaWN5ZGRXMTBnUFNCYkNna0pDU2QwWlhoMEp5QTlQaUFrZEdocGN5MCtiR0Z1WjNWaFoyVXRQbWRsZENnbmRHVjRkRjlqWVhKMEp5a3NDZ2tKQ1Nkb2NtVm1KeUE5UGlBa2RHaHBjeTArZFhKc0xUNXNhVzVyS0NkamFHVmphMjkxZEM5allYSjBKeXdnSjJ4aGJtZDFZV2RsUFNjZ0xpQWtkR2hwY3kwK1kyOXVabWxuTFQ1blpYUW9KMk52Ym1acFoxOXNZVzVuZFdGblpTY3BLUW9KQ1YwN0Nnb0pDU1JrWVhSaFd5ZGljbVZoWkdOeWRXMWljeWRkVzEwZ1BTQmJDZ2tKQ1NkMFpYaDBKeUE5UGlBa2RHaHBjeTArYkdGdVozVmhaMlV0UG1kbGRDZ25hR1ZoWkdsdVoxOTBhWFJzWlNjcExBb0pDUWtuYUhKbFppY2dQVDRnSkhSb2FYTXRQblZ5YkMwK2JHbHVheWduWTJobFkydHZkWFF2WTJobFkydHZkWFFuTENBbmJHRnVaM1ZoWjJVOUp5QXVJQ1IwYUdsekxUNWpiMjVtYVdjdFBtZGxkQ2duWTI5dVptbG5YMnhoYm1kMVlXZGxKeWtwQ2drSlhUc0tDZ2tKYVdZZ0tDRWtkR2hwY3kwK1kzVnpkRzl0WlhJdFBtbHpURzluWjJWa0tDa3BJSHNLQ1FrSkpHUmhkR0ZiSjNKbFoybHpkR1Z5SjEwZ1BTQWtkR2hwY3kwK2JHOWhaQzArWTI5dWRISnZiR3hsY2lnblkyaGxZMnR2ZFhRdmNtVm5hWE4wWlhJbktUc0tDUWw5SUdWc2MyVWdld29KQ1Fra1pHRjBZVnNuY21WbmFYTjBaWEluWFNBOUlDY25Pd29KQ1gwS0Nna0phV1lnS0NSMGFHbHpMVDVqZFhOMGIyMWxjaTArYVhOTWIyZG5aV1FvS1NBbUppQWtkR2hwY3kwK1kyOXVabWxuTFQ1blpYUW9KMk52Ym1acFoxOWphR1ZqYTI5MWRGOXdZWGx0Wlc1MFgyRmtaSEpsYzNNbktTa2dld29KQ1Fra1pHRjBZVnNuY0dGNWJXVnVkRjloWkdSeVpYTnpKMTBnUFNBa2RHaHBjeTArYkc5aFpDMCtZMjl1ZEhKdmJHeGxjaWduWTJobFkydHZkWFF2Y0dGNWJXVnVkRjloWkdSeVpYTnpKeWs3Q2drSmZTQmxiSE5sSUhzS0NRa0pKR1JoZEdGYkozQmhlVzFsYm5SZllXUmtjbVZ6Y3lkZElEMGdKeWM3Q2drSmZRb0tDUWxwWmlBb0pIUm9hWE10UG1OMWMzUnZiV1Z5TFQ1cGMweHZaMmRsWkNncElDWW1JQ1IwYUdsekxUNWpZWEowTFQ1b1lYTlRhR2x3Y0dsdVp5Z3BLU0I3Q2drSkNTUmtZWFJoV3lkemFHbHdjR2x1WjE5aFpHUnlaWE56SjEwZ1BTQWtkR2hwY3kwK2JHOWhaQzArWTI5dWRISnZiR3hsY2lnblkyaGxZMnR2ZFhRdmMyaHBjSEJwYm1kZllXUmtjbVZ6Y3ljcE93b0pDWDBnWld4elpTQjdDZ2tKQ1NSa1lYUmhXeWR6YUdsd2NHbHVaMTloWkdSeVpYTnpKMTBnUFNBbkp6c0tDUWw5Q2dvSkNXbG1JQ2drZEdocGN5MCtZMkZ5ZEMwK2FHRnpVMmhwY0hCcGJtY29LU2tnZXdvSkNRa2taR0YwWVZzbmMyaHBjSEJwYm1kZmJXVjBhRzlrSjEwZ1BTQWtkR2hwY3kwK2JHOWhaQzArWTI5dWRISnZiR3hsY2lnblkyaGxZMnR2ZFhRdmMyaHBjSEJwYm1kZmJXVjBhRzlrSnlrN0Nna0pmU0JsYkhObElIc0tDUWtKSkdSaGRHRmJKM05vYVhCd2FXNW5YMjFsZEdodlpDZGRJRDBnSnljN0Nna0pmUW9LQ1Frdkx5QkJRME10TURBeVFpQmhkWFJvYjNKcGMyVmtJSEpsWTJWcGRtVnlJSEJ5YjJacGJHVWdabUZzYkdKaFkyczZJR0VnYm05dUxXVnRjSFI1SUc5eVpHVnlMVzl1YkhrS0NRa3ZMeUJ2ZG1WeWNtbGtaU0IzYVc1ek95QnZkR2hsY25kcGMyVWdjM1JoY25RZ1puSnZiU0IwYUdVZ2JHOW5aMlZrTFdsdUlHTjFjM1J2YldWeUlIQnliMlpwYkdVdUNna0pKSEpsWTJWcGRtVnlYMjkyWlhKeWFXUmxJRDBnSkhSb2FYTXRQbk5sYzNOcGIyNHRQbVJoZEdGYkozSmtNVE5mY21WalpXbDJaWEpmYjNabGNuSnBaR1VuWFNBL1B5QmJYVHNLQ1Fra2NtVmpaV2wyWlhKZmIzWmxjbkpwWkdVZ1BTQnBjMTloY25KaGVTZ2tjbVZqWldsMlpYSmZiM1psY25KcFpHVXBJRDhnSkhKbFkyVnBkbVZ5WDI5MlpYSnlhV1JsSURvZ1cxMDdDZ2tKSkhCeWIyWnBiR1ZmWm1seWMzUnVZVzFsSUQwZ0pIUm9hWE10UG1OMWMzUnZiV1Z5TFQ1cGMweHZaMmRsWkNncElDWW1JRzFsZEdodlpGOWxlR2x6ZEhNb0pIUm9hWE10UG1OMWMzUnZiV1Z5TENBbloyVjBSbWx5YzNST1lXMWxKeWtLQ1FrSlB5QW9jM1J5YVc1bktTUjBhR2x6TFQ1amRYTjBiMjFsY2kwK1oyVjBSbWx5YzNST1lXMWxLQ2tLQ1FrSk9pQW5KenNLQ1Fra2NISnZabWxzWlY5c1lYTjBibUZ0WlNBOUlDUjBhR2x6TFQ1amRYTjBiMjFsY2kwK2FYTk1iMmRuWldRb0tTQW1KaUJ0WlhSb2IyUmZaWGhwYzNSektDUjBhR2x6TFQ1amRYTjBiMjFsY2l3Z0oyZGxkRXhoYzNST1lXMWxKeWtLQ1FrSlB5QW9jM1J5YVc1bktTUjBhR2x6TFQ1amRYTjBiMjFsY2kwK1oyVjBUR0Z6ZEU1aGJXVW9LUW9KQ1FrNklDY25Pd29KQ1NSdmRtVnljbWxrWlY5bWFYSnpkRzVoYldVZ1BTQjBjbWx0S0NoemRISnBibWNwS0NSeVpXTmxhWFpsY2w5dmRtVnljbWxrWlZzblptbHljM1J1WVcxbEoxMGdQejhnSnljcEtUc0tDUWtrYjNabGNuSnBaR1ZmYkdGemRHNWhiV1VnUFNCMGNtbHRLQ2h6ZEhKcGJtY3BLQ1J5WldObGFYWmxjbDl2ZG1WeWNtbGtaVnNuYkdGemRHNWhiV1VuWFNBL1B5QW5KeWtwT3dvSkNTUmtZWFJoV3lkamRYTjBiMjFsY2w5MFpXeGxjR2h2Ym1VblhTQTlJQ1IwYUdsekxUNWpkWE4wYjIxbGNpMCthWE5NYjJkblpXUW9LU0EvSUNoemRISnBibWNwSkhSb2FYTXRQbU4xYzNSdmJXVnlMVDVuWlhSVVpXeGxjR2h2Ym1Vb0tTQTZJQ2NuT3dvSkNTUmtZWFJoV3lkeVpXTmxhWFpsY2w5dmRtVnljbWxrWlY5bWFYSnpkRzVoYldVblhTQTlJQ1J2ZG1WeWNtbGtaVjltYVhKemRHNWhiV1VnSVQwOUlDY25JRDhnSkc5MlpYSnlhV1JsWDJacGNuTjBibUZ0WlNBNklDUndjbTltYVd4bFgyWnBjbk4wYm1GdFpUc0tDUWtrWkdGMFlWc25jbVZqWldsMlpYSmZiM1psY25KcFpHVmZiR0Z6ZEc1aGJXVW5YU0E5SUNSdmRtVnljbWxrWlY5c1lYTjBibUZ0WlNBaFBUMGdKeWNnUHlBa2IzWmxjbkpwWkdWZmJHRnpkRzVoYldVZ09pQWtjSEp2Wm1sc1pWOXNZWE4wYm1GdFpUc0tDUWtrWkdGMFlWc25jbVZqWldsMlpYSmZiM1psY25KcFpHVmZiV2xrWkd4bGJtRnRaU2RkSUQwZ0tITjBjbWx1Wnlrb0pISmxZMlZwZG1WeVgyOTJaWEp5YVdSbFd5ZHRhV1JrYkdWdVlXMWxKMTBnUHo4Z0p5Y3BPd29KQ1NSa1lYUmhXeWR5WldObGFYWmxjbDl2ZG1WeWNtbGtaVjkwWld4bGNHaHZibVVuWFNBOUlDaHpkSEpwYm1jcEtDUnlaV05sYVhabGNsOXZkbVZ5Y21sa1pWc25kR1ZzWlhCb2IyNWxKMTBnUHo4Z0p5Y3BPd29LQ1Fra1pHRjBZVnNuY0dGNWJXVnVkRjl0WlhSb2IyUW5YU0E5SUNSMGFHbHpMVDVzYjJGa0xUNWpiMjUwY205c2JHVnlLQ2RqYUdWamEyOTFkQzl3WVhsdFpXNTBYMjFsZEdodlpDY3BPd29KQ1NSa1lYUmhXeWRqYjI1bWFYSnRKMTBnUFNBa2RHaHBjeTArYkc5aFpDMCtZMjl1ZEhKdmJHeGxjaWduWTJobFkydHZkWFF2WTI5dVptbHliU2NwT3dvS0NRa2taR0YwWVZzblkyOXNkVzF1WDJ4bFpuUW5YU0E5SUNSMGFHbHpMVDVzYjJGa0xUNWpiMjUwY205c2JHVnlLQ2RqYjIxdGIyNHZZMjlzZFcxdVgyeGxablFuS1RzS0NRa2taR0YwWVZzblkyOXNkVzF1WDNKcFoyaDBKMTBnUFNBa2RHaHBjeTArYkc5aFpDMCtZMjl1ZEhKdmJHeGxjaWduWTI5dGJXOXVMMk52YkhWdGJsOXlhV2RvZENjcE93b0pDU1JrWVhSaFd5ZGpiMjUwWlc1MFgzUnZjQ2RkSUQwZ0pIUm9hWE10UG14dllXUXRQbU52Ym5SeWIyeHNaWElvSjJOdmJXMXZiaTlqYjI1MFpXNTBYM1J2Y0NjcE93b0pDU1JrWVhSaFd5ZGpiMjUwWlc1MFgySnZkSFJ2YlNkZElEMGdKSFJvYVhNdFBteHZZV1F0UG1OdmJuUnliMnhzWlhJb0oyTnZiVzF2Ymk5amIyNTBaVzUwWDJKdmRIUnZiU2NwT3dvSkNTUmtZWFJoV3lkbWIyOTBaWEluWFNBOUlDUjBhR2x6TFQ1c2IyRmtMVDVqYjI1MGNtOXNiR1Z5S0NkamIyMXRiMjR2Wm05dmRHVnlKeWs3Q2drSkpHUmhkR0ZiSjJobFlXUmxjaWRkSUQwZ0pIUm9hWE10UG14dllXUXRQbU52Ym5SeWIyeHNaWElvSjJOdmJXMXZiaTlvWldGa1pYSW5LVHNLQ2drSkpIUm9hWE10UG5KbGMzQnZibk5sTFQ1elpYUlBkWFJ3ZFhRb0pIUm9hWE10UG14dllXUXRQblpwWlhjb0oyTm9aV05yYjNWMEwyTm9aV05yYjNWMEp5d2dKR1JoZEdFcEtUc0tDWDBLZlFvPSJ9fQ==', true), true, 512, JSON_THROW_ON_ERROR);

function pay001_fail(string $message, int $code = 1): never {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit($code);
}

function pay001_restore(string $root, string $backup, array $manifest): void {
    foreach (array_keys($manifest) as $relative) {
        $saved = $backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($saved)) {
            @copy($saved, $target);
        }
    }
}

function pay001_lint(string $file): void {
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $status = 0;
    exec($command, $output, $status);
    if ($status !== 0) {
        throw new RuntimeException('php_l_failed file=' . $file . ' output=' . implode(' | ', $output));
    }
}

try {
    pay001_lint(__FILE__);
} catch (Throwable $error) {
    pay001_fail($error->getMessage());
}

if (!is_file($root . DIRECTORY_SEPARATOR . 'config.php')) {
    pay001_fail('run_from_opencart_root_config_missing');
}

$old_count = 0;
$new_count = 0;

foreach ($manifest as $relative => $spec) {
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($target)) {
        pay001_fail('target_missing file=' . $relative);
    }

    $hash = hash_file('sha256', $target);
    if (hash_equals($spec['new_sha256'], $hash)) {
        $new_count++;
        continue;
    }
    if (!hash_equals($spec['old_sha256'], $hash)) {
        pay001_fail('source_sha256_mismatch file=' . $relative . ' expected=' . $spec['old_sha256'] . ' actual=' . $hash);
    }

    $source = file_get_contents($target);
    if ($source === false) {
        pay001_fail('read_failed file=' . $relative);
    }
    $anchor_count = substr_count($source, $spec['anchor']);
    if ($anchor_count !== (int)$spec['anchor_count']) {
        pay001_fail('anchor_count file=' . $relative . ' expected=' . $spec['anchor_count'] . ' actual=' . $anchor_count);
    }
    $old_count++;
}

if ($new_count === count($manifest)) {
    echo 'already_applied=yes' . PHP_EOL;
    @unlink(__FILE__);
    exit(0);
}
if ($new_count > 0 || $old_count !== count($manifest)) {
    pay001_fail('mixed_source_state old=' . $old_count . ' new=' . $new_count);
}

$timestamp = date('Ymd-His');
$backup = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch_id . '-' . $timestamp;

foreach (array_keys($manifest) as $relative) {
    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $saved = $backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $saved_dir = dirname($saved);
    if (!is_dir($saved_dir) && !mkdir($saved_dir, 0775, true) && !is_dir($saved_dir)) {
        pay001_fail('backup_dir_failed path=' . $saved_dir);
    }
    if (!copy($target, $saved)) {
        pay001_fail('backup_failed file=' . $relative);
    }
}

echo 'cwd=' . $root . PHP_EOL;
echo 'time=' . date(DATE_ATOM) . PHP_EOL;
echo 'backup=' . $backup . PHP_EOL;

try {
    foreach ($manifest as $relative => $spec) {
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $content = base64_decode($spec['content_base64'], true);
        if ($content === false) {
            throw new RuntimeException('decode_failed file=' . $relative);
        }
        if (file_put_contents($target, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException('write_failed file=' . $relative);
        }
        if (!hash_equals($spec['new_sha256'], hash_file('sha256', $target))) {
            throw new RuntimeException('post_write_sha256_mismatch file=' . $relative);
        }
        echo 'changed_file=' . $relative . PHP_EOL;
    }

    foreach ($manifest as $relative => $spec) {
        if (!empty($spec['php_l'])) {
            pay001_lint($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        }
    }
} catch (Throwable $error) {
    pay001_restore($root, $backup, $manifest);
    pay001_fail($error->getMessage() . ' restored=yes');
}

echo 'php_l=ok' . PHP_EOL;
echo 'done=ok' . PHP_EOL;
@unlink(__FILE__);