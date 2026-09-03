(function () {
  let scanQueued = false;

  function getModelContext() {
    return document.modelContext || navigator.modelContext || null;
  }

  function updateStatus(modelContext) {
    const dot = document.querySelector('#hlStatus .hl-dot');
    const label = document.querySelector('#hlStatus span:last-child');

    if (!dot || !label) return;

    if (modelContext) {
      dot.classList.add('on');
      if (label.textContent !== 'WebMCP supported - agent ready') {
        label.textContent = 'WebMCP supported - agent ready';
      }
    } else {
      dot.classList.remove('on');
      if (label.textContent !== 'WebMCP is not available in this browser') {
        label.textContent = 'WebMCP is not available in this browser';
      }
    }
  }

  function registerFromNode(node) {
    if (node.dataset.webmcpRegistered === '1') return;

    let payload;
    try {
      payload = JSON.parse(node.textContent);
    } catch (err) {
      console.error('[webmcp-laravel] Invalid tool payload:', err);
      return;
    }

    const { componentId, tools } = payload;
    const modelContext = getModelContext();

    if (!modelContext) return;

    (tools || []).forEach((tool) => {
      modelContext.registerTool({
        name: tool.name,
        description: tool.description,
        inputSchema: tool.inputSchema,
        execute: async (input) => {
          const component = window.Livewire && window.Livewire.find(componentId);

          if (!component) {
            return {
              content: [{ type: 'text', text: 'The related Livewire component is no longer on the page.' }],
              isError: true,
            };
          }

          // Pass arguments to the Livewire method in JSON Schema property order.
          const orderedKeys = Object.keys(tool.inputSchema?.properties || {});
          const args = orderedKeys.map((key) => (input || {})[key]);

          try {
            const result = await component.call(tool.method, ...args);
            return {
              content: [{ type: 'text', text: typeof result === 'string' ? result : JSON.stringify(result) }],
            };
          } catch (err) {
            return {
              content: [{ type: 'text', text: 'Tool execution failed: ' + err.message }],
              isError: true,
            };
          }
        },
      });
    });

    node.dataset.webmcpRegistered = '1';
  }

  function scan() {
    updateStatus(getModelContext());
    document.querySelectorAll('script[data-webmcp-tools]').forEach(registerFromNode);
  }

  function queueScan() {
    if (scanQueued) return;

    scanQueued = true;
    setTimeout(() => {
      scanQueued = false;
      scan();
    }, 0);
  }

  // Rescan after Livewire morphs/navigations so tool registrations survive page updates.
  document.addEventListener('DOMContentLoaded', scan);
  document.addEventListener('livewire:init', scan);
  document.addEventListener('livewire:navigated', scan);
  document.addEventListener('livewire:morph.updated', scan);

  if (window.MutationObserver) {
    new MutationObserver(queueScan).observe(document.documentElement, {
      childList: true,
      subtree: true,
    });
  }
})();
