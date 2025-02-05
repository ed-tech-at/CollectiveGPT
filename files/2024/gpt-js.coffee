
lastKnownEtag = 0
session_id = ""

autoReload = 2 # 0.. of, 1.. ajax, 2.. websocket

loadText = () ->
  $.ajax "/api_gpt?getText=1",
    type: "POST"
    data: { },
    headers: { "If-None-Match": lastKnownEtag },
    error: (jqXHR, textStatus, errorThrown) ->
      $('body').append "AJAX Error: #{textStatus}"
      if autoReload == 1
        setTimeout ->
          loadText()
        , 5000
    success: (data, textStatus, jqXHR) ->
      if autoReload == 1
        setTimeout ->
          loadText()
        , 500
      if jqXHR.status == 200
        if (lastKnownEtag == jqXHR.getResponseHeader 'etagb')
          return
        lastKnownEtag = jqXHR.getResponseHeader 'etagb'
        $('#text').replaceWith data
        htmx.process(document.body)
      else if jqXHR.status == 304
        # console.log 'Cached data unchanged'
        return

getInCheckbox = (sender) ->
  if $("#agb").is(":checked")
    $("#getin").addClass("getin")
    $("#getin").removeClass("disable")
  else
    $("#getin").addClass("disable")
    $("#getin").removeClass("getin")

drawChart = (data) ->
  $('#chart').empty().append("<canvas id='myChart' width='400' height='400'></canvas>")
  ctx = document.getElementById('myChart').getContext('2d')
  myChart = new Chart(ctx, data)

startNchan = () ->
  # NchanSubscriber = require("nchan")
  opt = {
    subscriber: 'websocket',
    reconnect: undefined,
    shared: undefined
  }

  sub = new NchanSubscriber("wss://ed-tech.app/sub_id/?id=gpt2024p", opt)
  # console.log sub
  sub.on 'message', (message, message_metadata) ->
    # message is a string
    # message_metadata is a hash that may contain 'id' and 'content-type'
    # console.log message
    # console.log message_metadata
    msg = JSON.parse message

    console.log msg
    if msg.a == "p"
      loadText()
    if msg.a == "reset"
      if msg.session_id == session_id
        htmx.ajax('GET', '/api_gpt?resetUsername=2', '#main')
    if msg.a == "chart"
      $.ajax "/chart-config.json",
        type: "GET"
        data: { },
        error: (jqXHR, textStatus, errorThrown) ->
          $('body').append "AJAX Error: #{textStatus}"
        success: (data, textStatus, jqXHR) ->
          return drawChart(data)
    if msg.a == "stop"
      $.ajax "/",
        type: "GET"
        data: { },
        error: (jqXHR, textStatus, errorThrown) ->
          $('body').append "AJAX Error: #{textStatus}"
        success: (data, textStatus, jqXHR) ->
          $('body').html data
      sub.stop()
      return

    
    return

  sub.on "connect", (evt) ->
    console.log sub
    console.log evt
    loadText()

  sub.on "error", (evt, error_description) ->
    console.log "error"
    console.log sub
    console.log evt
    console.log error_description

  sub.start()
