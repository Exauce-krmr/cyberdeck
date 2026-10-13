// Page Labo : navigation par onglets. L'onglet ouvert est retenu (les actions rechargent la page), sans que ce soit essentiel.
const KEY = 'labTab';

const read = () => {
    try { return localStorage.getItem(KEY); } catch { return null; }
};
const write = (value) => {
    try { localStorage.setItem(KEY, value); } catch { /* navigation privée : on oublie simplement */ }
};

export default () => ({
    tab: '',
    tabs: [],

    init() {
        this.tabs = JSON.parse(this.$el.dataset.tabs);
        const wanted = location.hash.slice(1) || read();
        this.tab = this.tabs.includes(wanted) ? wanted : this.tabs[0];
    },

    show(id) {
        this.tab = id;
        write(id);
        history.replaceState(null, '', `#${id}`);
    },
});
