var $ = jQuery.noConflict();
const $chatChannels = $('#chat-search-channels');
const chatData = {
  chatOpen: false,
  channels: {
    group: channelDataFactory()
  }
};

// Handle Vidyo's group text chat
function handleChat(vidyoConnector) {
  vidyoConnector.RegisterMessageEventListener({
    onChatMessageAcknowledged: function(message) {
      if (message.type === 'VIDYO_CHATMESSAGETYPE_Chat') {
        const $message = getMessage(message, localParticipant, 'message-sent');
        appendMessageToChannel($message, 'group');
      }
      updateScroll();
    },
    onChatMessageReceived : function(participant, message) {
      let channelId = null;

      switch(message.type) {
        case 'VIDYO_CHATMESSAGETYPE_Chat':
          channelId = 'group';
          break;
        case 'VIDYO_CHATMESSAGETYPE_PrivateChat':
          channelId = participant.id;
          break;
        default:
          return;
      }
      const $message = getMessage(message, participant);
      appendMessageToChannel($message, channelId);

      if (!chatData.chatOpen) {
        const unreadMessagesTotal = Object.values(chatData.channels)
          .map((data) => data.unreadMessageCount)
          .reduce((sum, num) => sum + num, 0);

        if (unreadMessagesTotal > 0) {
          $("#new-message-notification")
            .text(unreadMessagesTotal > 99 ? '99+' : unreadMessagesTotal)
            .removeClass('hidden');
        }
      }
      updateScroll();
    }
  });
}

// Respond to the chat button being clicked
function onChatButtonClicked() {
  if (chatData.chatOpen) {
    $("#myForm")[0].style.display = "none";
    $("#renderer").addClass("rendererWithoutChat").removeClass("rendererWithChat");
  } else {
    $("#myForm")[0].style.display = "block";
    $("#renderer").removeClass("rendererWithoutChat").addClass("rendererWithChat");
    $("#new-message-notification").addClass("hidden").val('');
    updateScroll();
    $("#chatTabButton").click();
  }
  chatData.chatOpen = !chatData.chatOpen;
}

// Open either the Chat or Participants tab
function openTab(event) {
  tabName = event.data.tabName;
  color = event.data.color;
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablink");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].style.backgroundColor = "#DDD";
  }
  document.getElementById(tabName).style.display="grid";
  event.target.style.backgroundColor = color;
}

// Get's a new timestamp format HH:MM on a 12 hour clock
function getTimeStamp(timestamp = Date.now()) {
  const time = new Date(timestamp);
  hour = time.getHours() % 12 || 12;
  minutes = time.getMinutes() < 10 ? '0' + time.getMinutes() : time.getMinutes();
  meridies = Math.floor(time.getHours()/12) == 1 ? "PM" : "AM";
  return hour + ":" + minutes + ' ' + meridies;
}

// Scrolls to the bottom of the message box
function updateScroll() {
  setTimeout(function() {
    $('.chat-history').each(function() {
      this.scrollTop = this.scrollHeight;
    });
  }, 0);
}

// Send chat message to other participants
async function sendChatMessage(event) {
  const $li = $(event.target.closest('li'));
  const $input = $(event.target).parent().find('input');
  const channelId = $li.attr('data-channel-id');
  const textBody = $.trim($input.val());

  if (!textBody.trim().length) {
    return;
  }
  event.preventDefault();

  try {
    let isSent = false;

    if (channelId === 'group') {
      isSent = await vidyoConnector.SendChatMessage({ message: textBody });
    } else {
      const participant = chatData.channels[channelId]?.vidyoParticipant;
      isSent = await vidyoConnector.SendPrivateChatMessage({ participant, message: textBody });

      if (isSent) {
        const $message = getMessage({ body: textBody }, localParticipant, 'message-sent');
        appendMessageToChannel($message, channelId);
      }
    }
    if (isSent) {
      $input.val('');
    }
  } catch(err) {
    console.error(err);
  }
  updateScroll();
}

function resetChatData() {
  chatData.chatOpen = false;
  chatData.channels = { group: channelDataFactory() };
  $chatChannels.find('li:not([data-channel-id="group"])').remove();
  $chatChannels.find('.chat-history').empty();
  $chatChannels.find('li .unread-messages').each(function() {
    $(this).addClass('hidden').text('');
  });
  $('#new-message-notification').addClass('hidden').text('');
}

function addChannel(vidyoParticipant) {
  chatData.channels[vidyoParticipant.id] = channelDataFactory(vidyoParticipant);
  $chatChannels.append(`
    <li data-channel-id="${vidyoParticipant.id}" data-channel-name="${vidyoParticipant.name}">
      <div class="chat-channel-label" onclick="selectChannel(event)">
        <div class="avatar-placeholder">
          ${getInitials(vidyoParticipant.name)}
          <span class="unread-messages hidden">0</span>
        </div>
        <span class="chat-channel-name">
          ${vidyoParticipant.name}
          <br>
          <span class="chat-participant-left">
            Left the conference
          </span>
        </span>
      </div>
      <div class="chat-history-popup hidden">
        <div class="chat-history-header">
          <div class="back-button" onclick="closeChannel()"></div>
          <div class="avatar-placeholder">
            ${getInitials(vidyoParticipant.name)}
          </div>
          <span>${vidyoParticipant.name}</span>
        </div>
        <div class="chat-history-content">
          <div class="chat-history"></div>
        </div>
        <div class="chat-footer">
          <form class="message-composer" autocomplete="off" onsubmit="return false;">
            <input type="text" placeholder="Type your message here" required></input>
            <button type="submit" onclick="sendChatMessage(event)">
              <img src="../images/send_chat_message.svg" width="20px" height= "20px">
            </button>
          </form>
          <div class="participant-left-placeholder">
            ${vidyoParticipant.name} left the conference
            <br>
            You are not able to send messages.
          </div>
        </div>
      </div>
    </li>
  `);
  logToGroupChat(vidyoParticipant.name + ' joined the conference');
}

function activateChannel(vidyoParticipant) {
  if (chatData.channels[vidyoParticipant.id]) {
    $chatChannels.find(`li[data-channel-id="${vidyoParticipant.id}"]`).removeClass('participant-left');
  } else {
    addChannel(vidyoParticipant);
  }
}

function deactivateChannel(vidyoParticipant) {
  $chatChannels.find(`li[data-channel-id="${vidyoParticipant.id}"]`).addClass('participant-left');
  logToGroupChat(vidyoParticipant.name + ' left the conference');
}

function closeChannel() {
  $('.chat-history-popup').addClass('hidden');
}

function searchChannel(event) {
  const $li = $chatChannels.find('li');
  const query = event.target.value.match(/[\s\w\d]+/)?.[0]?.toLowerCase();

  $li.filter(function() {
    return this.getAttribute('data-channel-name')?.toLowerCase().match(query);
  }).removeClass('hidden');

  $li.filter(function() {
    return !this.getAttribute('data-channel-name')?.toLowerCase().match(query);
  }).addClass('hidden');
}

function selectChannel(event) {
  const li = event.target.closest('li');
  const channelId = $(li).attr('data-channel-id');

  if (chatData.channels[channelId]) {
    chatData.channels[channelId].unreadMessageCount = 0;
  }

  $(li).find('.unread-messages').text('').addClass('hidden');
  $chatChannels.find(`li:not([data-channel-id="${channelId}"]) .chat-history-popup`).addClass('hidden');
  $chatChannels.find(`li[data-channel-id="${channelId}"] .chat-history-popup`).removeClass('hidden');
  $(li).find('form input').focus();
}

function appendMessageToChannel($message, channelId) {
  const $channel = $chatChannels.find(`li[data-channel-id="${channelId}"]`);
  $channel.find('.chat-history').append($message);

  if ($channel.find('.chat-history-popup.hidden')[0] && chatData.channels[channelId]) {
    const unreadMessageCount = ++chatData.channels[channelId].unreadMessageCount;

    $channel.find('.unread-messages')
      .text(unreadMessageCount > 99 ? '99+' : unreadMessageCount)
      .removeClass('hidden');
  }
}

function getMessage(message, participant, classes = '') {
  const $messageBody = $('<p>').text(message.body);
  return $(`
    <div class="chat-message-wrapper ${classes}">
      <div class="message-avatar-wrapper">
        <div class="message-sender-avatar">
          <span>${getInitials(participant.name)}</span>
        </div>
      </div>
      <div class="message-content">
        <span class="message-header">
          ${participant.name}
        </span>
        <span class="message-bubble">
          ${$messageBody.html()}
          <br>
        </span>
        <span class="message-footer">
          <time datetime="${message.timestamp}">
            ${getTimeStamp(message.timestamp)}
          </time>
        </span>
      </div>
    </div>
  `);
}

function logToGroupChat(logMessage) {
  const $logMessage = $('<p class="chat-log">').text(`${getTimeStamp()} ${logMessage}`);
  $chatChannels.find(`li[data-channel-id="group"] .chat-history`).append($logMessage);
}

function getInitials(name = '') {
  return name.toUpperCase().split(/\W/).filter(w => w.length).slice(0, 2).reduce((acc, str) => {
    return acc += str[0];
  }, '');
}

function channelDataFactory(vidyoParticipant = null) {
  return {
    unreadMessageCount: 0,
    vidyoParticipant
  };
}
