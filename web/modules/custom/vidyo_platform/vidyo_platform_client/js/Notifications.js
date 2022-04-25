var $ = jQuery.noConflict();
(() => {
  const $notifications = $('<div class="notifications-container"></div>');
  const getStackSize = () => Math.floor((window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight) / 100);
  const handleResize = () => void $notifications.html($notifications.children().slice(0, getStackSize()));

  window.addEventListener('resize', handleResize);
  $(document.body).append($notifications);

  window.Notifications = {
    clear: () => {
      $notifications.empty();
    },
    toast: ({ message, image = '', timeout = 5000 }) => {
      if ($notifications.children().length >= getStackSize()) {
        $notifications.children().first().remove();
      }
      const $toast = $(`
      < div class = "notification" >
        < div class = "notification-image-wrapper" > ${image} < / div >
        < div class = "notification-message" > ${message} < / div >
      < / div > `);
      setTimeout(() => void $toast.remove(), timeout);
      $toast.click(() => void $toast.remove());
      $notifications.prepend($toast);
    }
  };
})();
