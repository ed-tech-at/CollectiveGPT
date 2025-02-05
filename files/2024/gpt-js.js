var autoReload, drawChart, getInCheckbox, lastKnownEtag, loadText, session_id, startNchan;

lastKnownEtag = 0;

session_id = "";

autoReload = 2; // 0.. of, 1.. ajax, 2.. websocket

loadText = function() {
  return $.ajax("/api_gpt?getText=1", {
    type: "POST",
    data: {},
    headers: {
      "If-None-Match": lastKnownEtag
    },
    error: function(jqXHR, textStatus, errorThrown) {
      $('body').append(`AJAX Error: ${textStatus}`);
      if (autoReload === 1) {
        return setTimeout(function() {
          return loadText();
        }, 5000);
      }
    },
    success: function(data, textStatus, jqXHR) {
      if (autoReload === 1) {
        setTimeout(function() {
          return loadText();
        }, 500);
      }
      if (jqXHR.status === 200) {
        if (lastKnownEtag === jqXHR.getResponseHeader('etagb')) {
          return;
        }
        lastKnownEtag = jqXHR.getResponseHeader('etagb');
        $('#text').replaceWith(data);
        return htmx.process(document.body);
      } else if (jqXHR.status === 304) {

      }
    }
  });
};

// console.log 'Cached data unchanged'
getInCheckbox = function(sender) {
  if ($("#agb").is(":checked")) {
    $("#getin").addClass("getin");
    return $("#getin").removeClass("disable");
  } else {
    $("#getin").addClass("disable");
    return $("#getin").removeClass("getin");
  }
};

drawChart = function(data) {
  var ctx, myChart;
  $('#chart').empty().append("<canvas id='myChart' width='400' height='400'></canvas>");
  ctx = document.getElementById('myChart').getContext('2d');
  return myChart = new Chart(ctx, data);
};

startNchan = function() {
  var opt, sub;
  // NchanSubscriber = require("nchan")
  opt = {
    subscriber: 'websocket',
    reconnect: void 0,
    shared: void 0
  };
  sub = new NchanSubscriber("wss://ed-tech.app/sub_id/?id=gpt2024p", opt);
  // console.log sub
  sub.on('message', function(message, message_metadata) {
    var msg;
    // message is a string
    // message_metadata is a hash that may contain 'id' and 'content-type'
    // console.log message
    // console.log message_metadata
    msg = JSON.parse(message);
    console.log(msg);
    if (msg.a === "p") {
      loadText();
    }
    if (msg.a === "reset") {
      if (msg.session_id === session_id) {
        htmx.ajax('GET', '/api_gpt?resetUsername=2', '#main');
      }
    }
    if (msg.a === "chart") {
      $.ajax("/chart-config.json", {
        type: "GET",
        data: {},
        error: function(jqXHR, textStatus, errorThrown) {
          return $('body').append(`AJAX Error: ${textStatus}`);
        },
        success: function(data, textStatus, jqXHR) {
          return drawChart(data);
        }
      });
    }
    if (msg.a === "stop") {
      $.ajax("/", {
        type: "GET",
        data: {},
        error: function(jqXHR, textStatus, errorThrown) {
          return $('body').append(`AJAX Error: ${textStatus}`);
        },
        success: function(data, textStatus, jqXHR) {
          return $('body').html(data);
        }
      });
      sub.stop();
      return;
    }
  });
  sub.on("connect", function(evt) {
    console.log(sub);
    console.log(evt);
    return loadText();
  });
  sub.on("error", function(evt, error_description) {
    console.log("error");
    console.log(sub);
    console.log(evt);
    return console.log(error_description);
  });
  return sub.start();
};


//# sourceMappingURL=gpt-js.js.map
//# sourceURL=coffeescript