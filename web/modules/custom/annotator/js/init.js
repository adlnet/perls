jQuery(function ($) {
  $.i18n.load(i18n_dict);
  // Select only full learning article nodes for annotator functionality.
  // See perls_learner.module for intended scope of annotator library.
  var annotator = $('article.c-node--full--learn-article').annotator()
  var annotatorData = annotator.data('annotator');
  if (!annotatorData) {
    return;
  }
  var xapiStoreHelper = new XAPIStoreHelper();
  // Customise the default plugin options.
  annotatorData.addPlugin('Permissions', {
    user: xapiStoreHelper.getActor(),
    showViewPermissionsCheckbox: false,
    showEditPermissionsCheckbox: false
  });
  annotator.annotator('addPlugin', 'AnnotatorViewer');
  annotator.annotator("addPlugin", "Touch");
  annotator.annotator('addPlugin', 'XAPIStateStore', {
    xapiStoreHelper: xapiStoreHelper
  });
  $('#anotacions-uoc-panel').slimscroll({ height: '100%' });
});
