// A write and the subsequent read-back are separate outcomes. Never invite a
// second write just because refreshing the screen failed after confirmation.
export function createOperationRunner() {
  let busy = false;
  return {
    get busy() { return busy; },
    async run({ execute, onConfirmed, refresh, onState }) {
      if (busy) return false;
      busy = true;
      try {
        onState("pending");
        const result = await execute();
        try {
          onState("confirmed", { result });
          await onConfirmed?.(result);
          await refresh?.(result);
          onState("success", { result });
        } catch (error) {
          onState("refresh-error", { result, error });
          return true;
        }
        return true;
      } catch (error) {
        onState("error", { error });
        return false;
      } finally {
        busy = false;
        onState("idle");
      }
    },
  };
}

export async function refreshUntilFresh({ refresh, isFresh, delays = [0, 400, 900, 1600], pause = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds)) }) {
  for (const delay of delays) {
    if (delay > 0) await pause(delay);
    await refresh();
    if (isFresh()) return true;
  }
  return false;
}
