import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';

export default function render(url, context) {
    const app = createSSRApp({
        render: () => h('div', 'Hello SSR'),
    });

    return renderToString(app);
}
