const xapi_store = new XAPIStoreHelper();

// store.js This is a basic store.
const store = new Vuex.Store({
  state: {
    count: 0,
    annotations: [],
  },
  mutations: {
    SET_ANNOTATIONS(state, annotations) {
      state.annotations = annotations;
    }
  },
  actions: {
    async fetch_annotations() {
      try {
        const response = await fetch('/api/annotations');
        const annotations = await response.json();
        return store.commit('SET_ANNOTATIONS', annotations);
      } catch {}
    },
    remove(store, annotation) {
      var annotations = store.state.annotations.filter(_annotation => _annotation.statement_id !== annotation.statement_id);
      return new Promise((resolve) => {
        store.commit('SET_ANNOTATIONS', annotations);
        resolve(annotation);
      }).then((annotation) => {
        return store.dispatch('persist', annotation);
      });
    },
    persist(state, annotation) {
      return new Promise((resolve) => {
        resolve();
        xapi_store.sendVoidedStatement(annotation);
      });
    }
  },
});

// app Vue instance
var app = new Vue({
  store,
  data: {},
  // computed properties
  computed: {
    annotations() {
      return this.$store.state.annotations;
    },
  },

  // methods that implement data logic.
  // note there's no DOM manipulation here at all.
  methods: {
    removeAnnotation: function (annotation) {
      this.$store.dispatch('remove', annotation);
    },
    goTo: function(node_url) {
      if (!node_url) {
        return;
      }
      window.location.href = node_url;
    },
  },

  // a custom directive to wait for the DOM to be updated
  // before focusing on the input field.
  // http://vuejs.org/guide/custom-directive.html
  directives: {
    'annotation-focus': function (el, value) {
      if (value) {
        el.focus()
      }
    }
  },

  mounted() {
    this.$store.dispatch('fetch_annotations');
  },
})

// handle routing
function onHashChange() {
  window.location.hash = ''
}

window.addEventListener('hashchange', onHashChange)
onHashChange()

// mount
app.$mount('.annotationApp')
