(function () {
  var __bind = function (fn, me) { return function () { return fn.apply(me, arguments); }; },
    __hasProp = {}.hasOwnProperty,
    __extends = function (child, parent) { for (var key in parent) { if (__hasProp.call(parent, key)) child[key] = parent[key]; } function ctor() { this.constructor = child; } ctor.prototype = parent.prototype; child.prototype = new ctor(); child.__super__ = parent.prototype; return child; },
    __indexOf = [].indexOf || function (item) { for (var i = 0, l = this.length; i < l; i++) { if (i in this && this[i] === item) return i; } return -1; }
    $=jQuery;

  Annotator.Plugin.XAPIStateStore = (function (_super) {
    __extends(XAPIStateStore, _super);

    XAPIStateStore.prototype.events = {
      'annotationCreated': 'annotationCreated',
      'annotationDeleted': 'annotationDeleted',
      'annotationUpdated': 'annotationUpdated'
    };

    XAPIStateStore.prototype.options = {
      annotationData: {},
      xapiStoreHelper: null,
    };

    function XAPIStateStore(element, options) {
      this._onError = __bind(this._onError, this);
      this.onLoadAnnotations = __bind(this.onLoadAnnotations, this);
      this.getAnnotations = __bind(this.getAnnotations, this);
      if (options.xapiStoreHelper) {
        this.xapiStoreHelper = options.xapiStoreHelper;
      }
      XAPIStateStore.__super__.constructor.apply(this, arguments);
      this.annotations = [];
    }

    XAPIStateStore.prototype.pluginInit = function () {
      if (!Annotator.supported()) {
        return;
      }
      if (!this.xapiStoreHelper) {
        return;
      }
      return this.getAnnotations();
    };

    XAPIStateStore.prototype.getAnnotations = function () {
      return this.loadAnnotations();
    };

    XAPIStateStore.prototype.annotationCreated = function (annotation) {
      this.registerAnnotation(annotation);
      var statement_id = this.xapiStoreHelper.sendAnnotatedStatement(annotation);
      annotation.statement_id = statement_id;
      this.xapiStoreHelper.persist(this.annotations);
    };

    XAPIStateStore.prototype.annotationUpdated = function (annotation) {
      if (__indexOf.call(this.annotations, annotation) >= 0) {
        var statement_id = this.xapiStoreHelper.sendUpdatedStatement(annotation);
        annotation.statement_id = statement_id;
        this.xapiStoreHelper.persist(this.annotations);
      }
    };

    XAPIStateStore.prototype.annotationDeleted = function (annotation) {
      if (__indexOf.call(this.annotations, annotation) >= 0) {
        this.xapiStoreHelper.sendVoidedStatement(annotation);
        var oldAnnotations = this.unregisterAnnotation(annotation);
        this.xapiStoreHelper.persist(this.annotations);
        return oldAnnotations;
      }
    };

    XAPIStateStore.prototype.registerAnnotation = function (annotation) {
      return this.annotations.push(annotation);
    };

    XAPIStateStore.prototype.unregisterAnnotation = function (annotation) {
      return this.annotations.splice(this.annotations.indexOf(annotation), 1);
    };

    XAPIStateStore.prototype.loadAnnotations = function () {
      return this.xapiStoreHelper.read(this.onLoadAnnotations);
    };

    XAPIStateStore.prototype.onLoadAnnotations = function (data) {
      if (data == null) {
        data = [];
      }
      var annotationMap = {};
      var newData = [];
      var ref = this.annotations;
      for (var i = 0; i < ref.length; i++) {
        var a = ref[i];
        annotationMap[a.id] = a;
      }
      for (var j = 0; j < data.length; j++) {
        var a = data[j];
        if (annotationMap[a.id]) {
          var annotation = annotationMap[a.id];
          this.updateAnnotation(annotation, a);
        } else {
          newData.push(a);
        }
      }
      this.annotations = this.annotations.concat(newData);
      return this.annotator.loadAnnotations(newData.slice());
    };

    return XAPIStateStore;

  })(Annotator.Plugin);

}).call(this);
