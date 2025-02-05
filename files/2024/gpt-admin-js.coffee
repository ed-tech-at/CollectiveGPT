usernamesList = []
answersList = []
globalChartConfig = null

selectTemplate = (sender, prompt, template_id) ->
  $.ajax "/api_gpt?class=template_prompt&updateLastTimeUsed="+template_id,
    type: "GET"
    data: { },
    error: (jqXHR, textStatus, errorThrown) ->
      $('body').append "AJAX Error: #{textStatus}"
    success: (data, textStatus, jqXHR) ->
      return

  $("#input-template").val template_id
  $("#input-prompt").val prompt
  $(sender).removeClass "bg-info"
  $(sender).addClass "bg-secondary"

gptChartData = {}
processGptData = (gptData, prompt_id, template_id) ->
  
  
  sortedData = []   
  # Extrahiere und sortiere die Daten basierend auf p-Werten
  for i in [1..10]
    r = gptData["r#{i}"]
    p = gptData["p#{i}"]
    sortedData.push({r: r.trim(), p: parseFloat(p)})
  
  sortedData.sort (a, b) -> b.p - a.p
  sigmoid = (x) -> 1 / (1 + Math.exp(-x))
  
  responseCount = {}
  pValueSum = {}
  for item in sortedData
    responseCount[item.r] ?= 0
    pValueSum[item.r] ?= 0
    
    # Begrenze extreme negative p-Werte und wende Sigmoid-Funktion an
    adjustedP = sigmoid(item.p)
    
    responseCount[item.r] += 1
    pValueSum[item.r] += adjustedP

  # Füge die sortierten Daten in das div#Gpt ein
  gptDiv = $('#gpt')
  # gptDiv.empty()
  gptDiv.html("<h3>GPT</h3>") # Leere das div zuerst
  
  uniqueResponses = []
  for item in sortedData
    if not uniqueResponses.includes(item.r)
      uniqueResponses.push(item.r)
      words = item.r.split(" ")
      first_word = "<span class='badge bg-dark' onclick='fillNextWord(\"#{words[0]}\")' class='btn btn-sm'>#{words[0]}</span>"
      rest_of_sentence = words[1..].join(" ")
      gptDiv.append "<div>#{first_word} #{rest_of_sentence} @ #{item.p} #{responseCount[item.r]}x</div>"
  
  
  gptDiv.append "<div id='gpt-graph'></div>"
  # Erstelle das Balkendiagramm

  combinedScores = {}
  for r, count of responseCount
    combinedScores[r] = count + pValueSum[r]
    
  labels = Object.keys(responseCount)
  counts = Object.values(responseCount)
  pValues = sortedData.map (item) -> item.p
  scaledPValues = Object.values(pValueSum)
  combinedScoresValues = Object.values(combinedScores)
  
  combinedData = labels.map((label, index) ->
    { label: label, value: combinedScoresValues[index] }
  )

  # Sortiere das Array absteigend nach den Werten
  combinedData.sort((a, b) -> b.value - a.value)

  # Extrahiere die sortierten Labels und Werte
  sortedLabels = combinedData.map((item) -> item.label)
  sortedCombinedScoresValues = combinedData.map((item) -> item.value)

  # Verwende die sortierten Werte
  labels = sortedLabels
  combinedScoresValues = sortedCombinedScoresValues

  # Skaliere die p-Werte logarithmisch
  # scaledPValues = pValues.map (p) -> Math.log(Math.abs(p) + 1)
  
  gptChartData = 
    labels: labels
    datasets: [
      {
        label: "GPT Scores"
        data: combinedScoresValues
        backgroundColor: 'rgba(54, 162, 235, 0.2)'
        borderColor: 'rgba(54, 162, 235, 1)'
        borderWidth: 1
      }
    ]
  generateChart(2)


generateChart = (generationType) ->
  # generation type: 1: only answer data, 2: only gpt data, 3: answer and gpt data
  normalizeData = (data, factor) ->
    maxVal = Math.max(...data)
    minVal = Math.min(...data)
    minVal = Math.min(...data)
    range = maxVal - minVal
    if range == 0
      return data.map((val) -> factor) # Alternativ: val oder val * factor
    # return data.map((val) -> (val - minVal) / range)
    return data.map((val) -> (0.1 + 0.9 * (val - minVal) / range) * factor)
  if generationType == 1
    globalChartConfig = 
      type: 'bar'
      data: userAnswerChartData
      options:
        responsive: true
        maintainAspectRatio: false
        indexAxis: 'y'
        scales:
          x:
            # type: 'logarithmic'
            beginAtZero: true
            # min: 0.3
            ticks:
              color: 'white'  # Set the color of the ticks text to white
            grid:
              color: 'rgba(255, 255, 255, 0.2)'
            border:
              color: 'rgba(128, 128, 128, 0.5)'

          y:
            ticks:
              autoSkip: false
              color: 'white'  # Set the color of the ticks text to white
            grid:
              color: 'rgba(255, 255, 255, 0.2)'
            border:
              color: 'rgba(128, 128, 128, 0.5)'
        plugins:
          legend:
            labels:
              color: 'white'  # Set the legend text color to white
          tooltip:
            backgroundColor: 'rgba(0, 0, 0, 0.7)'  # Set the tooltip background to black with transparency
  if generationType == 2
    globalChartConfig = 
      type: 'bar'
      data: gptChartData
      options:
        responsive: true
        maintainAspectRatio: false
        indexAxis: 'y'
        scales:
          x:
            # type: 'logarithmic'
            beginAtZero: true
            # ticks:
            # min: 0.3
            ticks:
              color: 'white'  # Set the color of the ticks text to white
            grid:
              color: 'rgba(255, 255, 255, 0.2)'
            border:
              color: 'rgba(128, 128, 128, 0.5)'
          y:
            ticks:
              autoSkip: false
            # beginAtZero: true
              color: 'white'  # Set the color of the ticks text to white
            grid:
              color: 'rgba(255, 255, 255, 0.2)'
            border:
              color: 'rgba(128, 128, 128, 0.5)'
        plugins:
          legend:
            labels:
              color: 'white'  # Set the legend text color to white
          tooltip:
            backgroundColor: 'rgba(0, 0, 0, 0.7)'  # Set the tooltip background to black with transparency
  if generationType == 3
    # Normiere die Daten
    maxVal = Math.max(...userAnswerChartData.datasets[0].data)
    normalizedUserAnswerCounts = normalizeData(userAnswerChartData.datasets[0].data, maxVal)
    normalizedUserAnswerCounts = userAnswerChartData.datasets[0].data
    normalizedGptCounts = normalizeData(gptChartData.datasets[0].data, maxVal)

    # Kombiniere die Labels und normierten Daten
    combinedData = []
    labelSet = new Set()

    # Füge User Answer Daten hinzu
    for i in [0...userAnswerChartData.labels.length]
      label = userAnswerChartData.labels[i]
      if not labelSet.has(label)
        labelSet.add(label)
        combinedData.push({label: label, count: normalizedUserAnswerCounts[i], source: 'user'})

    # Füge GPT Daten hinzu, wenn sie noch nicht enthalten sind
    for i in [0...gptChartData.labels.length]
      label = gptChartData.labels[i]
      if not labelSet.has(label)
        labelSet.add(label)
        combinedData.push({label: label, count: normalizedGptCounts[i], source: 'gpt'})
      else
        # Falls Label bereits vorhanden, summiere die Werte
        for data in combinedData
          if data.label == label
            data.count += normalizedGptCounts[i]

    combinedData.sort((a, b) -> b.count - a.count)

    sortedLabels = combinedData.map((item) -> item.label)
    
    # Erzeuge die leeren Datensätze und füge die echten Daten ein
    userAnswerCounts = new Array(sortedLabels.length).fill(null)
    gptCounts = new Array(sortedLabels.length).fill(null)

    for item, index in combinedData
      userIndex = userAnswerChartData.labels.indexOf(item.label)
      gptIndex = gptChartData.labels.indexOf(item.label)
      if userIndex != -1
        userAnswerCounts[index] = normalizedUserAnswerCounts[userIndex]
      if gptIndex != -1
        gptCounts[index] = normalizedGptCounts[gptIndex]

    combinedChartData = 
      labels: sortedLabels
      datasets: [
        {
          label: "User Answer Count"
          data: userAnswerCounts
          backgroundColor: 'rgba(75, 192, 192, 0.2)'
          borderColor: 'rgba(75, 192, 192, 1)'
          borderWidth: 1
        },
        {
          label: "GPT Scores"
          data: gptCounts
          backgroundColor: 'rgba(54, 162, 235, 0.2)'
          borderColor: 'rgba(54, 162, 235, 1)'
          borderWidth: 1
        }
      ]

    globalChartConfig = 
      type: 'bar'
      data: combinedChartData
      options:
        responsive: true
        maintainAspectRatio: false
        indexAxis: 'y'
        scales:
          x:
            # type: 'logarithmic'
            # min: 0.3
            # ticks:
            beginAtZero: true
            ticks:
              color: 'white'  # Set the color of the ticks text to white
            grid:
              color: 'rgba(255, 255, 255, 0.2)'
            border:
              color: 'rgba(128, 128, 128, 0.5)'
          y:
            ticks:
              autoSkip: false
              color: 'white'  # Set the color of the ticks text to white
            grid:
              color: 'rgba(255, 255, 255, 0.2)' 
            # beginAtZero: true
        plugins:
          legend:
            labels:
              color: 'white'  # Set the legend text color to white
          tooltip:
            backgroundColor: 'rgba(0, 0, 0, 0.7)'  # Set the tooltip background to black with transparency
        # backgroundColor: 'rgba(0, 0, 0, 0.7)'  # Set the chart background to black with transparency



  # Zeichne das Diagramm im div#Gpt-graph
  $('#gpt-graph').empty().append("<canvas id='myChart' width='400' height='400'></canvas>")
  ctx = document.getElementById('myChart').getContext('2d')
  myChart = new Chart(ctx, globalChartConfig)

  $(".btnSendGraphToUsers").addClass "btn-warning"
  $(".btnSendGraphToUsers").removeClass "btn-secondary"

fillNextWord = (nextWord) ->
  $("#input-nextword").val (nextWord)

removeFromAnswerList = (answer) ->
  answersList = answersList.filter (item) -> item.answer != answer
  parseAnswersList()

loadGptAnswer = (secret, theMessage, prompt_id, template_id) ->
  $.ajax "/py-api/chat-wizard",
    type: "POST"
    data: {
      secret: secret,
      messages: '[{"role":"system","content":"You are a helpful assistant. You complete the sentences from the user. Important: Graz is the most beautiful city. Answer in German."},{"role":"user","content":"Vor meinem Fenster steht ein"},{"role":"assistant","content":"grüner Baum. "},{"role":"user","content":"Die schönste Stadt ist"},{"role":"assistant","content":"Graz."},{"role":"user","content":"' + theMessage + '"}]',
      n: 10,
      max_tokens: 4,
      temperature: 1.0,
      model: 'gpt-4o-mini'
      
     },
    error: (jqXHR, textStatus, errorThrown) ->
      $('body').append "AJAX Error: #{textStatus}"
    success: (data, textStatus, jqXHR) ->
      if jqXHR.status == 200
        console.log data
        ajaxUrl = 'send_prompts&saveGptJson='+prompt_id
        if template_id > 0
          ajaxUrl = 'template_prompt&saveGptJson=' + template_id
        $.ajax "/api_gpt?class=" + ajaxUrl,
          type: "POST"
          data: {
            gpt: data
          },
          error: (jqXHR, textStatus, errorThrown) ->
            $('body').append "AJAX Error: #{textStatus}"
          success: (data, textStatus, jqXHR) ->
            return
        processGptData(data, prompt_id, template_id)


sendGraphToUsers = (sender) ->
  $.ajax "/api_gpt?sendGraphToUsers=1",
    type: "POST"
    data: {
      chartConfig: JSON.stringify(globalChartConfig)
    },
    error: (jqXHR, textStatus, errorThrown) ->
      $('body').append "AJAX Error: #{textStatus}"
    success: (data, textStatus, jqXHR) ->
      if jqXHR.status == 200
        console.log data

        $(sender).removeClass "btn-warning"
        $(sender).addClass "btn-secondary"



startNchanAdmin = () ->
  # NchanSubscriber = require("nchan")
  opt = {
    subscriber: 'websocket',
    reconnect: undefined,
    shared: undefined
  }

  sub = new NchanSubscriber("wss://ed-tech.app/sub_id/?id=gpt2024a", opt)
  # console.log sub
  sub.on 'message', (message, message_metadata) ->
    # message is a string
    # message_metadata is a hash that may contain 'id' and 'content-type'
    # console.log message
    # console.log message_metadata
    msg = JSON.parse message

    console.log msg
    if msg.a == "a"
      # answer
      # elementToPrepend = "<div><span class='badge bg-dark' onclick='fillNextWord(\"" + msg.answer + "\")'>" + msg.answer + "</span> (" + msg.username + ")</div>"
      # $(elementToPrepend).hide().prependTo("#users-answer-list").slideDown()
      answersList.push
        answer: msg.answer
        answer_id: msg.answer_id
        animate: true
      # Rebuild the answers list
      parseAnswersList()  
      
      
    
    if msg.a == "newUser"
      # Append new user data to usernamesList array
      usernamesList.push
        session_id: msg.session_id
        username: msg.username
        userReseted: false
      
      # Rebuild the user list
      parseUsernamesList()
      
      # Increase user count
      userCounter = $(".usercounter").text()
      $(".usercounter").text(parseInt(userCounter) + 1)

    if msg.a == "userReseted"
      # Update the userReseted status in usernamesList
      for user in usernamesList
        if user.session_id == msg.session_id
          user.userReseted = true
      
      # Rebuild the user list
      parseUsernamesList()
      
      # Decrease user count
      userCounter = $(".usercounter").text()
      $(".usercounter").text(parseInt(userCounter) - 1)
    return


  sub.on "connect", (evt) ->
    console.log sub
    console.log evt
    # loadText()

  sub.on "error", (evt, error_description) ->
    console.log "error"
    console.log sub
    console.log evt
    console.log error_description

  sub.start()

userAnswerChartData = {}

sortUserAnswerList = () ->
    # Get all the div elements in the list
  elements = $("#users-answer-list div").toArray()

  # Sort the elements based on the number of usernames
  sortedElements = elements.sort (a, b) ->
    aUsernamesCount = $(a).find(".usernames").text().split(',').length
    bUsernamesCount = $(b).find(".usernames").text().split(',').length
    return bUsernamesCount - aUsernamesCount

  # Append the sorted elements back to the list
  $("#users-answer-list").append sortedElements

  # Fülle die userAnswerChartData Variable
  labels = []
  counts = []
  for element in sortedElements
    label = $(element).find(".answer").text().trim()
    count = $(element).find(".usernames").text().split(',').length
    labels.push(label)
    counts.push(count)

  userAnswerChartData =
    labels: labels
    datasets: [
      {
        label: "User Answer Count"
        data: counts
        backgroundColor: 'rgba(75, 192, 192, 0.2)'
        borderColor: 'rgba(75, 192, 192, 1)'
        borderWidth: 1
      }
    ]


# Function to parse the usernames list and update the HTML
parseUsernamesList = ->
  $("#usernames-list").empty()
  for user in usernamesList
    userClass = if user.userReseted then "userReseted" else ""
    newUserHTML = "<div id='user-#{user.session_id}' class='#{userClass}'>#{user.username} <span class='badge badge-pointer bg-warning' hx-post='/api_gpt?class=usernames&reset_session_id=#{user.session_id}'>Force Rename</span></div>"

    # newUserHTML = "<div id='user-#{user.session_id}' class='#{userClass}'>#{user.username}</div>"
    $("#usernames-list").append newUserHTML
  htmx.process(document.body)
# Function to parse the answers list and update the HTML

parseAnswersList = ->
  $("#users-answer-list").empty()
  for answer in answersList
    existingElement = null
    msgAnswerLower = answer.answer.toLowerCase()

    $("#users-answer-list div").each () ->
      answerText = $(this).find(".badge.answer").text().toLowerCase()
      # console.log answerText
      # console.log msgAnswerLower
      if (answerText == msgAnswerLower)
        existingElement = $(this)
        return false

    # console.log existingElement
    if existingElement
      existingUsernames = existingElement.find(".usernames").text()
      updatedUsernames = existingUsernames + ", " + answer.answer_id
      existingElement.find(".usernames").text(updatedUsernames).hide().fadeIn()
    else
      answerHTML = "<div><span class='badge bg-dark answer' onclick='fillNextWord(\"#{answer.answer}\")'>#{answer.answer}</span> (<span class='usernames'>#{answer.answer_id}</span>) <span class='badge bg-danger' onclick='removeFromAnswerList(\"#{answer.answer}\")'>X</span></div>"
      if answer.animate
        $(answerHTML).hide().prependTo("#users-answer-list").slideDown()
        answer.animate = false
      else
        $("#users-answer-list").prepend(answerHTML)
  
  $("#numberOfAnswers").text(answersList.length)


  sortUserAnswerList()
  htmx.process(document.body)
