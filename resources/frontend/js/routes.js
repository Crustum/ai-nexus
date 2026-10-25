import Discovery from './screens/Discovery.vue';
import Playground from './screens/Playground.vue';
import History from './screens/History.vue';

export default [
    { path: '/', name: 'discovery', component: Discovery },
    { path: '/playground/:agent?', name: 'playground', component: Playground, props: true },
    { path: '/history', name: 'history', component: History },
];
