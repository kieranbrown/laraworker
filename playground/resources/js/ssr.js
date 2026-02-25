import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';

const app = createSSRApp({
    render: () => h('div', 'Hello SSR'),
});

renderToString(app).then(html => {
    console.log(html);
});
