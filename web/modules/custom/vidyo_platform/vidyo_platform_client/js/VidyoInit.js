(function ($) {
  function onVidyoClientLoaded(status) {
    console.log("Status: " + status.state + "Description: " + status.description);
    switch (status.state) {
      case "READY":    // The library is operating normally
        $("#connectionStatus").html("Ready to Connect");
        $("#helper").addClass("hidden");
        window.VC = new window.VidyoClientLib.VidyoClient('', () => {
          // After the VidyoClient is successfully initialized a global VC object will become available
          // All of the VidyoConnector gui and logic is implemented in VidyoConnector.js
          StartVidyoConnector(VC, window.VCUtils ? window.VCUtils.params.webrtc : 'true');
        });
        break;

      case "RETRYING": // The library operating is temporarily paused
        $("#connectionStatus").html("Temporarily unavailable retrying in " + status.nextTimeout / 1000 + " seconds");
        break;

      case "FAILED":   // The library operating has stopped
        ShowFailed(status);
        $("#connectionStatus").html("Failed: " + status.description);
        break;

      case "FAILEDVERSION":   // The library operating has stopped
        UpdateHelperPaths(status);
        ShowFailedVersion(status);
        $("#connectionStatus").html("Failed: " + status.description);
        break;

      case "NOTAVAILABLE": // The library is not available
        UpdateHelperPaths(status);
        $("#connectionStatus").html(status.description);
        break;
    }
    return true; // Return true to reload the plugins if not available
  }
  function UpdateHelperPaths(status) {
    $("#helperPlugInDownload").attr("href", status.downloadPathPlugIn);
    $("#helperAppDownload").attr("href", status.downloadPathApp);
  }
  function ShowFailed(status) {
    var helperText = '';
    // Display the error
    helperText += '<h2>An error occurred, please reload</h2>';
    helperText += '<p>' + status.description + '</p>';

    $("#helperText").html(helperText);
    $("#failedText").html(helperText);
    $("#failed").removeClass("hidden");
  }
  function ShowFailedVersion(status) {
    var helperText = '';
    // Display the error
    helperText += '<h4>Please Download a new plugIn and restart the browser</h4>';
    helperText += '<p>' + status.description + '</p>';

    $("#helperText").html(helperText);
  }

  function loadVidyoClientLibrary(webrtc, plugin) {
    // If webrtc, then set webrtcLogLevel
    var webrtcLogLevel = "";
    if (webrtc) {
      // Set the WebRTC log level to either: 'info' (default), 'error', or 'none'
      webrtcLogLevel = '&webrtcLogLevel=info';
    }
    //We need to ensure we're loading the VidyoClient library and listening for the callback.
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = 'https://static.vidyo.io/latest/javascript/VidyoClient/VidyoClient.js?onload=onVidyoClientLoaded&webrtc=' + webrtc + '&plugin=' + plugin + webrtcLogLevel;
    document.getElementsByTagName('head')[0].appendChild(script);
  }

  function loadNativeScipWebRTCVidyoClientLibrary() {
    // Assumes that this file is hosted in the Hookflash build environment
    // TODO: update path
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = '../latest_build/VidyoClient.js';
    script.onload = function () {
      onVidyoClientLoaded({ state: 'READY', description: 'Native SCIP + WebRTC' });
    };
    document.getElementsByTagName('head')[0].appendChild(script);
    var style = document.createElement('link');
    style.rel = 'stylesheet';
    style.type = 'text/css';
    style.href = '../latest_build/VidyoClient.css';
    document.getElementsByTagName('head')[0].appendChild(style);
  }

  function joinViaBrowser() {
    $("#helperText").html("Loading...");
    $("#helperPicker").addClass("hidden");
    loadVidyoClientLibrary(true, false);
  }

  function joinViaPlugIn() {
    $("#helperText").html("Don't have the PlugIn?");
    $("#helperPicker").addClass("hidden");
    $("#helperPlugIn").removeClass("hidden");
    loadVidyoClientLibrary(false, true);
  }

  function joinViaElectron() {
    $("#helperText").html("Electron...");
    $("#helperPicker").addClass("hidden");
    loadVidyoClientLibrary(false, true);
  }

  function joinViaApp() {
    $("#helperText").html("Don't have the app?");
    $("#helperPicker").addClass("hidden");
    $("#helperApp").removeClass("hidden");
    var protocolHandlerLink = 'vidyoconnector://' + window.location.search;
    /* launch */
    $("#helperAppLoader").attr('src', protocolHandlerLink);
    loadVidyoClientLibrary(false, false);
  }

  function joinViaOtherApp() {
    $("#helperText").html("Don't have the app?");
    $("#helperPicker").addClass("hidden");
    $("#helperOtherApp").removeClass("hidden");
    var protocolHandlerLink = 'vidyoconnector://' + window.location.search;
    /* launch */
    $("#helperOtherAppLoader").attr('src', protocolHandlerLink);
    loadVidyoClientLibrary(false, false);
  }

  function joinViaNativeScipWebRTCApp() {
    let alink = document.getElementById('joinBtn');
    let imgClickJoin = document.getElementById('imgClickJoin');
    alink.classList.add('not-active');
    alink.removeAttribute("href");
    imgClickJoin.removeAttribute("onclick");
    alink.innerHTML = '<div class="loader"></div>';
    loadNativeScipWebRTCVidyoClientLibrary();
  }

  function loadHelperOptions() {
    var userAgent = navigator.userAgent || navigator.vendor || window.opera;
    // Opera 8.0+
    var isOpera = (userAgent.includes("Opera") || userAgent.includes('OPR'));
    // Firefox
    var isFirefox = userAgent.includes("Firefox");
    // Chrome 1+
    var isChrome = userAgent.includes("Chrome");
    // Safari
    var isSafari = !isChrome && userAgent.includes("Safari");
    // AppleWebKit
    var isAppleWebKit = !isSafari && !isChrome && userAgent.includes("AppleWebKit");
    // Internet Explorer 6-11
    var isIE = (userAgent.includes("MSIE")) || (!!document.documentMode == true);
    // Edge 20+
    var isEdge = !isIE && !!window.StyleMedia;
    // Check if Mac
    var isMac = navigator.platform.includes('Mac');
    // Check if Windows
    var isWin = navigator.platform.includes('Win');
    // Check if Linux
    var isLinux = navigator.platform.includes('Linux');
    // Check if Android
    var isAndroid = userAgent.includes("android");

    if (!isMac && !isWin && !isLinux) {
      /* Mobile App*/
      if (isAndroid) {
        $("#joinViaApp").removeClass("hidden");
      } else {
        $("#joinViaOtherApp").removeClass("hidden");
      }
      if (isChrome || isSafari) {
        /* Supports WebRTC */
        $("#joinViaBrowser").removeClass("hidden");

        // Native Xmpp WebRTC client only supports Chrome and Safari currently
        $("#joinViaBrowserNativeXmppWebRTC").removeClass("hidden");
      }
    } else {
      /* Desktop App */
      $("#joinViaApp").removeClass("hidden");

      if (isChrome || isFirefox) {
        /* Supports WebRTC */
        $("#joinViaBrowser").removeClass("hidden");
      }

      if (isChrome || isSafari || isFirefox) {
        // Native Xmpp WebRTC client only supports Chrome currently
        $("#joinViaBrowserNativeXmppWebRTC").removeClass("hidden");
      }

      if (isSafari || isFirefox || (isAppleWebKit && isMac) || (isIE && !isEdge)) {
        /* Supports Plugins */
        $("#joinViaPlugIn").removeClass("hidden");
      }
    }

    if (isSafari) {
      $("#enableUnifiedPlanCbx").addClass('hidden');
      $("#windowSharesContainer").addClass('hidden');
      $("#monitorSharesContainer").addClass('hidden');
    } else {
      $("#windowSharesSafari").addClass('hidden');
      $("#monitorSharesSafari").addClass('hidden');
    }
  }

  // Runs when the page loads
  $(function () {
    loadHelperOptions();
    onVidyoClientLoaded({ state: 'READY', description: 'Native SCIP + WebRTC' });
    const status = document.getElementById('offline-banner');
    function updateOnlineStatus(event) {
      status.classList.toggle('hidden', navigator.onLine);
    }
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
  });
})(jQuery);
