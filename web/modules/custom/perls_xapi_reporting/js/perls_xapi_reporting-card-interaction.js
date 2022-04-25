/**
 * Send statement for flash and tip cards.
 *
 */

(function ($, Drupal) {
  let answeredQuestion = [];

  Drupal.behaviors.xapiCardInteraction = {
    attach: function (context, settings) {
      // XAPI interactions need to work on Slick carousel and on pages without.
      // Slick carousel page on load.
      let slick = $(".js-slick-slider > ul", context);
      $.each(slick, function (index, slick) {
        let cards = $(".slick-slide article", slick);
        let slickId = $(this).attr("slick_id");
        checkElementInView(slickId, cards);
      });
      // Slick carousel on scroll
      $(slick).on("afterChange", function (event, slick, currentSlide) {
        let touchedSliderId = slick.instanceUid;
        let cards = $(".slick-slide article", this);
        checkElementInView(touchedSliderId, cards);
      });

      // Pages without slick carousel.
      if (!$(slick).length) {
        let cards = $("article.c-node--card", context).not($(".js-slick-slider > ul .c-node--card, .js-slick-slider > ul .c-node--tile", context));
        // If no slick id we can just make it 0.
        if ($(cards).length) {
          checkElementInView(0, cards);
        }

        // When the card open in modal.
        if ($(context).hasClass('c-node--card')) {
          cardVisible(context);
        }
      }

      // Catch the card flip event.
      $(".c-node--flash-card .c-card__view.js-toggle")
        .once()
        .on("click", function () {
        let cardId = $(this).closest("article").attr("node-id");
        sendLrsRequest(["xapi_interacted_state"], cardId, {});
      });

      // Quiz:User clicked to Try again button.
      $(".quiz-button-try-again").on("click", function () {
        $(this).closest("article").attr('step-in', Date.now());
      });

      // Quiz:User clicked to one of answer options.
      $(".c-quiz__question")
        .once("answerlistener")
        .on("click", function (element) {
          let slickId = $(this).closest(".slick-slider").length
            ? $(this).closest(".slick-slider").attr("slick_id")
            : 0;
          quizOptionChoose(slickId, this);
        });

      // Card stacks should listen for stack advance events.
      $(".c-stack", context)
        .once("testposition")
        .on("afterStackAdvance", function (event) {
          updateFeedback($(this).closest("article.c-node--test"));
          let cards = $(".c-node--quiz", $(this));
          let slickId = $(this).closest(".slick-slider").length
            ? $(this).closest(".slick-slider").attr("slick_id")
            : 0;
          checkElementInView(slickId, cards);
        });

      // Card stacks tests also need to listen for reset(try again) event.
      $(".c-stack", context)
        .once("testreset")
        .on("onStackReset", function (event) {
          // Reset correct answer count.
          $(this).attr("correct_count", 0);
          // Reset the quiz cards to show questions.
          $(".this-is-active", this).removeClass("this-is-active");
          let cards = $(".c-node--quiz", $(this));
          let slickId = $(this).closest(".slick-slider").length
            ? $(this).closest(".slick-slider").attr("slick_id")
            : 0;
          checkElementInView(slickId, cards);
        });
    },
  };

  /**
   * Check to see which cards are visible on the page.
   **/
  function checkElementInView(carouselId, cards) {
    let windowWidth = $(window).width();
    $.each(cards, function (index, card_object) {
      // In view.
      $is_part_of_test = $(card_object).parent().hasClass("quiz-in-test");
      // The stack animation can put the individual cards outside of screen causing an error.
      // It works better to use the test object for stack of cards.
      let card_or_test = $(card_object).closest("article.c-node--test").length
        ? $(card_object).closest("article.c-node--test")
        : card_object;
      $is_top_item_on_test = $(card_object).parent().hasClass("top");

      if (
        $(card_or_test).offset().left >= 0 &&
        $(card_or_test).offset().left < windowWidth &&
        (!$is_part_of_test || $is_top_item_on_test)
      ) {
        // If step-in isn't set this card is newly visible.
        if ($(card_object).attr('step-in') === undefined) {
          cardVisible(card_object);
        }
      } else {
        // If step-in is set this card used to be visible.
        if ($(card_object).attr('step-in') !== undefined) {
          cardOutOfView(carouselId, card_object);
        }
      }
    });
  }

  /**
   * Send a viewed XAPI statment if a cards is visible.
   **/
  function cardVisible(card) {
    // Add step in to card when it becomes visible.
    $(card).attr('step-in', Date.now());
  }

  /**
   * Send a skipped/experienced XAPI statment if a card was visible and then goes out of view.
   **/
  function cardOutOfView(carouselId, card) {
    $(card).removeAttr("step-in");
  }

  /**
   * This handlers is fired when a quiz option is choosen.
   **/
  function quizOptionChoose(carouselId, element) {
    let data = [];
    let cardId = $(element).closest("article").attr("node-id");
    // Store the answered questions.
    let index = answeredQuestion.indexOf(cardId);
    if (index === -1) {
      answeredQuestion.push(cardId);
    }

    // Start to generate XAPI statements.
    let answerData = {};
    let verbTypeList = [];
    verbTypeList.push("xapi_quiz_asked_state");
    verbTypeList.push("xapi_quiz_question_answered");
    // Check to see if quiz is part of a test.
    let parent = $(element).closest("article.c-node--test");
    // We treat quiz and test differently here.
    if (parent.length > 0) {
      answerData.parent = parent.attr("node-id");
      answerData.registration = parent.attr("test-uuid");
      // If first quiz in test send attempted on test.
      if ($(element).closest("article").parent().hasClass("first-card")) {
        $(".perls-test", parent).attr("correct_count", 0);
        data.push(sendTestAttempted(parent));
      }
    } else {
      answerData.parent = cardId;
      answerData.registration = $(element).closest("article").attr("quiz-uuid");
      verbTypeList.push("xapi_test_attempted_state");
      verbTypeList.push("xapi_assessment_completed");
    }
    // Add the selected answer to the xapi statement.
    answerData.answer_number = $(element).parent().attr("option-counter");
    answerData.answer = $(element).text().trim();

    // A correct or incorrect answer have different statements.
    if ($(".o-icon--correct", $(element).next()).length > 0) {
      answerData.success = "true";
      let correct = parseInt(
        $(".perls-test", parent).attr("correct_count")
      );
      // If card is quiz not in a test we have passed the assessment.
      if (parent.length < 1) {
        verbTypeList.push("xapi_assessment_passed");
      } else {
        // Keep track of score locally so we can include in outgoing statements.
        answerData.score = correct + 1;
        $(".perls-test", parent).attr("correct_count", correct + 1);
      }
    } else {
      answerData.success = "false";
      if (parent.length < 1) {
        verbTypeList.push("xapi_assessment_failed");
      } else {
        answerData.score = $(".perls-test", parent).attr("correct_count");
      }
    }
    // Add duration to xapi statement.
    let stepIn = $(element).closest("article").attr("step-in");
    answerData.duration = stepIn ? Date.now() - stepIn : 0;
    data.push(packetLrsData(verbTypeList, cardId, answerData));

    // Check to see if it is last question in test. If it is we need to send pass fail for test.
    if (
      parent.length > 0 &&
      $(element).closest("article").parent().hasClass("last-card")
    ) {
      let testData = {};
      let testVerbs = [];
      let node_id = parent.attr("node-id");
      testData.registration = parent.attr("test-uuid");
      testVerbs.push("xapi_assessment_completed");
      let correct = $(".perls-test", parent).attr("correct_count");
      let max = $(".perls-test", parent).attr("question_count");
      let pass_mark = $(".perls-test", parent).attr("pass_mark");
      if (correct / max >= pass_mark) {
        testVerbs.push("xapi_assessment_passed");
        testData.success = true;
      } else {
        testVerbs.push("xapi_assessment_failed");
        testData.success = false;
      }

      testData.score = correct;
      let testStepIn = $(parent).attr("step-in");
      testData.duration = testStepIn ? Date.now() - testStepIn : 0;
      data.push(packetLrsData(testVerbs, node_id, testData));
    }
    // Send an array of states.
    sendLrsArray(data);
  }

  /**
   * Fetch the test results from the server.
   */
  function updateFeedback(test) {
    // If we are on the last card we also want to update the results card so we do it here.
    let node_id = test.attr("node-id");
    // We only update the feedback as it is about to show.
    if ($(".results-card", test).parent().hasClass("top")) {
      $.get(Drupal.url("node/" + node_id), function (data) {
        // Update Feedback
        $(".perls-test .results-card .feedback", test).replaceWith(
          $(data).find(".perls-test .results-card .feedback")
        );
        // If you are showing the results card also update test-uuid
        if ($(".results-card", test).parent().hasClass("top")) {
          test.attr(
            "test-uuid",
            $(data).find("article.c-node--test").attr("test-uuid")
          );
        }
      });
    }
  }

  /**
   * Format a test attempted statement.
   **/
  function sendTestAttempted(test) {
    node_id = $(test).attr("node-id");
    testUUid = $(test).attr("test-uuid");
    return packetLrsData(["xapi_test_attempted_state"], node_id, {
      registration: testUUid,
    });
  }

  /**
   * A convience function to run both packet data and send data.
   **/
  function sendLrsRequest(stateIds, content, requestData) {
    let data = packetLrsData(stateIds, content, requestData);
    sendLrsArray(data);
  }

  /**
   * Create a clean json object out of the xapi data.
   **/
  function packetLrsData(stateIds, content, requestData) {
    return {
      state_ids: stateIds,
      content: content,
      extra_data: requestData,
    };
  }

  /**
   * Ajax call to push the xapi data to server.
   **/
  function sendLrsArray(data) {
    $.ajax({
      url: Drupal.url("perls-xapi/send-report"),
      type: "POST",
      dataType: "json",
      data: JSON.stringify(data),
      success(results) { },
    });
  }
})(jQuery, Drupal);
