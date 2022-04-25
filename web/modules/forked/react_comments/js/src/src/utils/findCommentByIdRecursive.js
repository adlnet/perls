export default function findCommentByIdRecursive(id, commentTree) {
  let result = null;
  commentTree.forEach(function(el, i) {
    if (el.id === id) {
      result = el;
    }
    else if (el.replies) {
      el = findCommentByIdRecursive(id, el.replies);
      if (el) result = el;
    }
  });
  return result;
};
