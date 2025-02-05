(function() {
  var answersList, fillNextWord, generateChart, globalChartConfig, gptChartData, loadGptAnswer, parseAnswersList, parseUsernamesList, processGptData, removeFromAnswerList, selectTemplate, sendGraphToUsers, sortUserAnswerList, startNchanAdmin, userAnswerChartData, usernamesList;

  usernamesList = [];

  answersList = [];

  globalChartConfig = null;

  selectTemplate = function(sender, prompt, template_id) {
    $.ajax("/api_gpt?class=template_prompt&updateLastTimeUsed=" + template_id, {
      type: "GET",
      data: {},
      error: function(jqXHR, textStatus, errorThrown) {
        return $('body').append(`AJAX Error: ${textStatus}`);
      },
      success: function(data, textStatus, jqXHR) {}
    });
    $("#input-template").val(template_id);
    $("#input-prompt").val(prompt);
    $(sender).removeClass("bg-info");
    return $(sender).addClass("bg-secondary");
  };

  gptChartData = {};

  processGptData = function(gptData, prompt_id, template_id) {
    var adjustedP, combinedData, combinedScores, combinedScoresValues, count, counts, first_word, gptDiv, i, item, j, k, l, labels, len, len1, name, name1, p, pValueSum, pValues, r, responseCount, rest_of_sentence, scaledPValues, sigmoid, sortedCombinedScoresValues, sortedData, sortedLabels, uniqueResponses, words;
    sortedData = [];

// Extrahiere und sortiere die Daten basierend auf p-Werten
    for (i = j = 1; j <= 10; i = ++j) {
      r = gptData[`r${i}`];
      p = gptData[`p${i}`];
      sortedData.push({
        r: r.trim(),
        p: parseFloat(p)
      });
    }
    sortedData.sort(function(a, b) {
      return b.p - a.p;
    });
    sigmoid = function(x) {
      return 1 / (1 + Math.exp(-x));
    };
    responseCount = {};
    pValueSum = {};
    for (k = 0, len = sortedData.length; k < len; k++) {
      item = sortedData[k];
      if (responseCount[name = item.r] == null) {
        responseCount[name] = 0;
      }
      if (pValueSum[name1 = item.r] == null) {
        pValueSum[name1] = 0;
      }
      
      // Begrenze extreme negative p-Werte und wende Sigmoid-Funktion an
      adjustedP = sigmoid(item.p);
      responseCount[item.r] += 1;
      pValueSum[item.r] += adjustedP;
    }
    // Füge die sortierten Daten in das div#Gpt ein
    gptDiv = $('#gpt');
    // gptDiv.empty()
    gptDiv.html("<h3>GPT</h3>"); // Leere das div zuerst
    uniqueResponses = [];
    for (l = 0, len1 = sortedData.length; l < len1; l++) {
      item = sortedData[l];
      if (!uniqueResponses.includes(item.r)) {
        uniqueResponses.push(item.r);
        words = item.r.split(" ");
        first_word = `<span class='badge bg-dark' onclick='fillNextWord("${words[0]}")' class='btn btn-sm'>${words[0]}</span>`;
        rest_of_sentence = words.slice(1).join(" ");
        gptDiv.append(`<div>${first_word} ${rest_of_sentence} @ ${item.p} ${responseCount[item.r]}x</div>`);
      }
    }
    gptDiv.append("<div id='gpt-graph'></div>");
    // Erstelle das Balkendiagramm
    combinedScores = {};
    for (r in responseCount) {
      count = responseCount[r];
      combinedScores[r] = count + pValueSum[r];
    }
    labels = Object.keys(responseCount);
    counts = Object.values(responseCount);
    pValues = sortedData.map(function(item) {
      return item.p;
    });
    scaledPValues = Object.values(pValueSum);
    combinedScoresValues = Object.values(combinedScores);
    combinedData = labels.map(function(label, index) {
      return {
        label: label,
        value: combinedScoresValues[index]
      };
    });
    // Sortiere das Array absteigend nach den Werten
    combinedData.sort(function(a, b) {
      return b.value - a.value;
    });
    // Extrahiere die sortierten Labels und Werte
    sortedLabels = combinedData.map(function(item) {
      return item.label;
    });
    sortedCombinedScoresValues = combinedData.map(function(item) {
      return item.value;
    });
    // Verwende die sortierten Werte
    labels = sortedLabels;
    combinedScoresValues = sortedCombinedScoresValues;
    // Skaliere die p-Werte logarithmisch
    // scaledPValues = pValues.map (p) -> Math.log(Math.abs(p) + 1)
    gptChartData = {
      labels: labels,
      datasets: [
        {
          label: "GPT Scores",
          data: combinedScoresValues,
          backgroundColor: 'rgba(54, 162, 235, 0.2)',
          borderColor: 'rgba(54, 162, 235, 1)',
          borderWidth: 1
        }
      ]
    };
    return generateChart(2);
  };

  generateChart = function(generationType) {
    var combinedChartData, combinedData, ctx, data, gptCounts, gptIndex, i, index, item, j, k, l, label, labelSet, len, len1, m, maxVal, myChart, normalizeData, normalizedGptCounts, normalizedUserAnswerCounts, ref, ref1, sortedLabels, userAnswerCounts, userIndex;
    // generation type: 1: only answer data, 2: only gpt data, 3: answer and gpt data
    normalizeData = function(data, factor) {
      var maxVal, minVal, range;
      maxVal = Math.max(...data);
      minVal = Math.min(...data);
      minVal = Math.min(...data);
      range = maxVal - minVal;
      if (range === 0) {
        return data.map(function(val) {
          return factor; // Alternativ: val oder val * factor
        });
      }
      // return data.map((val) -> (val - minVal) / range)
      return data.map(function(val) {
        return (0.1 + 0.9 * (val - minVal) / range) * factor;
      });
    };
    if (generationType === 1) {
      globalChartConfig = {
        type: 'bar',
        data: userAnswerChartData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: 'y',
          scales: {
            x: {
              // type: 'logarithmic'
              beginAtZero: true,
              // min: 0.3
              ticks: {
                color: 'white' // Set the color of the ticks text to white
              },
              grid: {
                color: 'rgba(255, 255, 255, 0.2)'
              },
              border: {
                color: 'rgba(128, 128, 128, 0.5)'
              }
            },
            y: {
              ticks: {
                autoSkip: false,
                color: 'white' // Set the color of the ticks text to white
              },
              grid: {
                color: 'rgba(255, 255, 255, 0.2)'
              },
              border: {
                color: 'rgba(128, 128, 128, 0.5)'
              }
            }
          },
          plugins: {
            legend: {
              labels: {
                color: 'white' // Set the legend text color to white
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.7)' // Set the tooltip background to black with transparency
            }
          }
        }
      };
    }
    if (generationType === 2) {
      globalChartConfig = {
        type: 'bar',
        data: gptChartData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: 'y',
          scales: {
            x: {
              // type: 'logarithmic'
              beginAtZero: true,
              // ticks:
              // min: 0.3
              ticks: {
                color: 'white' // Set the color of the ticks text to white
              },
              grid: {
                color: 'rgba(255, 255, 255, 0.2)'
              },
              border: {
                color: 'rgba(128, 128, 128, 0.5)'
              }
            },
            y: {
              ticks: {
                autoSkip: false,
                // beginAtZero: true
                color: 'white' // Set the color of the ticks text to white
              },
              grid: {
                color: 'rgba(255, 255, 255, 0.2)'
              },
              border: {
                color: 'rgba(128, 128, 128, 0.5)'
              }
            }
          },
          plugins: {
            legend: {
              labels: {
                color: 'white' // Set the legend text color to white
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.7)' // Set the tooltip background to black with transparency
            }
          }
        }
      };
    }
    if (generationType === 3) {
      // Normiere die Daten
      maxVal = Math.max(...userAnswerChartData.datasets[0].data);
      normalizedUserAnswerCounts = normalizeData(userAnswerChartData.datasets[0].data, maxVal);
      normalizedUserAnswerCounts = userAnswerChartData.datasets[0].data;
      normalizedGptCounts = normalizeData(gptChartData.datasets[0].data, maxVal);
      // Kombiniere die Labels und normierten Daten
      combinedData = [];
      labelSet = new Set();
// Füge User Answer Daten hinzu
      for (i = j = 0, ref = userAnswerChartData.labels.length; (0 <= ref ? j < ref : j > ref); i = 0 <= ref ? ++j : --j) {
        label = userAnswerChartData.labels[i];
        if (!labelSet.has(label)) {
          labelSet.add(label);
          combinedData.push({
            label: label,
            count: normalizedUserAnswerCounts[i],
            source: 'user'
          });
        }
      }
// Füge GPT Daten hinzu, wenn sie noch nicht enthalten sind
      for (i = k = 0, ref1 = gptChartData.labels.length; (0 <= ref1 ? k < ref1 : k > ref1); i = 0 <= ref1 ? ++k : --k) {
        label = gptChartData.labels[i];
        if (!labelSet.has(label)) {
          labelSet.add(label);
          combinedData.push({
            label: label,
            count: normalizedGptCounts[i],
            source: 'gpt'
          });
        } else {
// Falls Label bereits vorhanden, summiere die Werte
          for (l = 0, len = combinedData.length; l < len; l++) {
            data = combinedData[l];
            if (data.label === label) {
              data.count += normalizedGptCounts[i];
            }
          }
        }
      }
      combinedData.sort(function(a, b) {
        return b.count - a.count;
      });
      sortedLabels = combinedData.map(function(item) {
        return item.label;
      });
      
      // Erzeuge die leeren Datensätze und füge die echten Daten ein
      userAnswerCounts = new Array(sortedLabels.length).fill(null);
      gptCounts = new Array(sortedLabels.length).fill(null);
      for (index = m = 0, len1 = combinedData.length; m < len1; index = ++m) {
        item = combinedData[index];
        userIndex = userAnswerChartData.labels.indexOf(item.label);
        gptIndex = gptChartData.labels.indexOf(item.label);
        if (userIndex !== -1) {
          userAnswerCounts[index] = normalizedUserAnswerCounts[userIndex];
        }
        if (gptIndex !== -1) {
          gptCounts[index] = normalizedGptCounts[gptIndex];
        }
      }
      combinedChartData = {
        labels: sortedLabels,
        datasets: [
          {
            label: "User Answer Count",
            data: userAnswerCounts,
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
          },
          {
            label: "GPT Scores",
            data: gptCounts,
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
          }
        ]
      };
      globalChartConfig = {
        type: 'bar',
        data: combinedChartData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: 'y',
          scales: {
            x: {
              // type: 'logarithmic'
              // min: 0.3
              // ticks:
              beginAtZero: true,
              ticks: {
                color: 'white' // Set the color of the ticks text to white
              },
              grid: {
                color: 'rgba(255, 255, 255, 0.2)'
              },
              border: {
                color: 'rgba(128, 128, 128, 0.5)'
              }
            },
            y: {
              ticks: {
                autoSkip: false,
                color: 'white' // Set the color of the ticks text to white
              },
              grid: {
                color: 'rgba(255, 255, 255, 0.2)'
              }
            }
          },
          
          // beginAtZero: true
          plugins: {
            legend: {
              labels: {
                color: 'white' // Set the legend text color to white
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.7)' // Set the tooltip background to black with transparency
            }
          }
        }
      };
    }
    // backgroundColor: 'rgba(0, 0, 0, 0.7)'  # Set the chart background to black with transparency

    // Zeichne das Diagramm im div#Gpt-graph
    $('#gpt-graph').empty().append("<canvas id='myChart' width='400' height='400'></canvas>");
    ctx = document.getElementById('myChart').getContext('2d');
    myChart = new Chart(ctx, globalChartConfig);
    $(".btnSendGraphToUsers").addClass("btn-warning");
    return $(".btnSendGraphToUsers").removeClass("btn-secondary");
  };

  fillNextWord = function(nextWord) {
    return $("#input-nextword").val(nextWord);
  };

  removeFromAnswerList = function(answer) {
    answersList = answersList.filter(function(item) {
      return item.answer !== answer;
    });
    return parseAnswersList();
  };

  loadGptAnswer = function(secret, theMessage, prompt_id, template_id) {
    return $.ajax("/py-api/chat-wizard", {
      type: "POST",
      data: {
        secret: secret,
        messages: '[{"role":"system","content":"You are a helpful assistant. You complete the sentences from the user. Important: Graz is the most beautiful city. Answer in German."},{"role":"user","content":"Vor meinem Fenster steht ein"},{"role":"assistant","content":"grüner Baum. "},{"role":"user","content":"Die schönste Stadt ist"},{"role":"assistant","content":"Graz."},{"role":"user","content":"' + theMessage + '"}]',
        n: 10,
        max_tokens: 4,
        temperature: 1.0,
        model: 'gpt-4o-mini'
      },
      error: function(jqXHR, textStatus, errorThrown) {
        return $('body').append(`AJAX Error: ${textStatus}`);
      },
      success: function(data, textStatus, jqXHR) {
        var ajaxUrl;
        if (jqXHR.status === 200) {
          console.log(data);
          ajaxUrl = 'send_prompts&saveGptJson=' + prompt_id;
          if (template_id > 0) {
            ajaxUrl = 'template_prompt&saveGptJson=' + template_id;
          }
          $.ajax("/api_gpt?class=" + ajaxUrl, {
            type: "POST",
            data: {
              gpt: data
            },
            error: function(jqXHR, textStatus, errorThrown) {
              return $('body').append(`AJAX Error: ${textStatus}`);
            },
            success: function(data, textStatus, jqXHR) {}
          });
          return processGptData(data, prompt_id, template_id);
        }
      }
    });
  };

  sendGraphToUsers = function(sender) {
    return $.ajax("/api_gpt?sendGraphToUsers=1", {
      type: "POST",
      data: {
        chartConfig: JSON.stringify(globalChartConfig)
      },
      error: function(jqXHR, textStatus, errorThrown) {
        return $('body').append(`AJAX Error: ${textStatus}`);
      },
      success: function(data, textStatus, jqXHR) {
        if (jqXHR.status === 200) {
          console.log(data);
          $(sender).removeClass("btn-warning");
          return $(sender).addClass("btn-secondary");
        }
      }
    });
  };

  startNchanAdmin = function() {
    var opt, sub;
    // NchanSubscriber = require("nchan")
    opt = {
      subscriber: 'websocket',
      reconnect: void 0,
      shared: void 0
    };
    sub = new NchanSubscriber("wss://ed-tech.app/sub_id/?id=gpt2024a", opt);
    // console.log sub
    sub.on('message', function(message, message_metadata) {
      var j, len, msg, user, userCounter;
      // message is a string
      // message_metadata is a hash that may contain 'id' and 'content-type'
      // console.log message
      // console.log message_metadata
      msg = JSON.parse(message);
      console.log(msg);
      if (msg.a === "a") {
        // answer
        // elementToPrepend = "<div><span class='badge bg-dark' onclick='fillNextWord(\"" + msg.answer + "\")'>" + msg.answer + "</span> (" + msg.username + ")</div>"
        // $(elementToPrepend).hide().prependTo("#users-answer-list").slideDown()
        answersList.push({
          answer: msg.answer,
          answer_id: msg.answer_id,
          animate: true
        });
        // Rebuild the answers list
        parseAnswersList();
      }
      if (msg.a === "newUser") {
        // Append new user data to usernamesList array
        usernamesList.push({
          session_id: msg.session_id,
          username: msg.username,
          userReseted: false
        });
        
        // Rebuild the user list
        parseUsernamesList();
        
        // Increase user count
        userCounter = $(".usercounter").text();
        $(".usercounter").text(parseInt(userCounter) + 1);
      }
      if (msg.a === "userReseted") {
// Update the userReseted status in usernamesList
        for (j = 0, len = usernamesList.length; j < len; j++) {
          user = usernamesList[j];
          if (user.session_id === msg.session_id) {
            user.userReseted = true;
          }
        }
        
        // Rebuild the user list
        parseUsernamesList();
        
        // Decrease user count
        userCounter = $(".usercounter").text();
        $(".usercounter").text(parseInt(userCounter) - 1);
      }
    });
    sub.on("connect", function(evt) {
      console.log(sub);
      return console.log(evt);
    });
    // loadText()
    sub.on("error", function(evt, error_description) {
      console.log("error");
      console.log(sub);
      console.log(evt);
      return console.log(error_description);
    });
    return sub.start();
  };

  userAnswerChartData = {};

  sortUserAnswerList = function() {
    var count, counts, element, elements, j, label, labels, len, sortedElements;
    // Get all the div elements in the list
    elements = $("#users-answer-list div").toArray();
    // Sort the elements based on the number of usernames
    sortedElements = elements.sort(function(a, b) {
      var aUsernamesCount, bUsernamesCount;
      aUsernamesCount = $(a).find(".usernames").text().split(',').length;
      bUsernamesCount = $(b).find(".usernames").text().split(',').length;
      return bUsernamesCount - aUsernamesCount;
    });
    // Append the sorted elements back to the list
    $("#users-answer-list").append(sortedElements);
    // Fülle die userAnswerChartData Variable
    labels = [];
    counts = [];
    for (j = 0, len = sortedElements.length; j < len; j++) {
      element = sortedElements[j];
      label = $(element).find(".answer").text().trim();
      count = $(element).find(".usernames").text().split(',').length;
      labels.push(label);
      counts.push(count);
    }
    return userAnswerChartData = {
      labels: labels,
      datasets: [
        {
          label: "User Answer Count",
          data: counts,
          backgroundColor: 'rgba(75, 192, 192, 0.2)',
          borderColor: 'rgba(75, 192, 192, 1)',
          borderWidth: 1
        }
      ]
    };
  };

  // Function to parse the usernames list and update the HTML
  parseUsernamesList = function() {
    var j, len, newUserHTML, user, userClass;
    $("#usernames-list").empty();
    for (j = 0, len = usernamesList.length; j < len; j++) {
      user = usernamesList[j];
      userClass = user.userReseted ? "userReseted" : "";
      newUserHTML = `<div id='user-${user.session_id}' class='${userClass}'>${user.username} <span class='badge badge-pointer bg-warning' hx-post='/api_gpt?class=usernames&reset_session_id=${user.session_id}'>Force Rename</span></div>`;
      // newUserHTML = "<div id='user-#{user.session_id}' class='#{userClass}'>#{user.username}</div>"
      $("#usernames-list").append(newUserHTML);
    }
    return htmx.process(document.body);
  };

  // Function to parse the answers list and update the HTML
  parseAnswersList = function() {
    var answer, answerHTML, existingElement, existingUsernames, j, len, msgAnswerLower, updatedUsernames;
    $("#users-answer-list").empty();
    for (j = 0, len = answersList.length; j < len; j++) {
      answer = answersList[j];
      existingElement = null;
      msgAnswerLower = answer.answer.toLowerCase();
      $("#users-answer-list div").each(function() {
        var answerText;
        answerText = $(this).find(".badge.answer").text().toLowerCase();
        // console.log answerText
        // console.log msgAnswerLower
        if (answerText === msgAnswerLower) {
          existingElement = $(this);
          return false;
        }
      });
      // console.log existingElement
      if (existingElement) {
        existingUsernames = existingElement.find(".usernames").text();
        updatedUsernames = existingUsernames + ", " + answer.answer_id;
        existingElement.find(".usernames").text(updatedUsernames).hide().fadeIn();
      } else {
        answerHTML = `<div><span class='badge bg-dark answer' onclick='fillNextWord("${answer.answer}")'>${answer.answer}</span> (<span class='usernames'>${answer.answer_id}</span>) <span class='badge bg-danger' onclick='removeFromAnswerList("${answer.answer}")'>X</span></div>`;
        if (answer.animate) {
          $(answerHTML).hide().prependTo("#users-answer-list").slideDown();
          answer.animate = false;
        } else {
          $("#users-answer-list").prepend(answerHTML);
        }
      }
    }
    $("#numberOfAnswers").text(answersList.length);
    sortUserAnswerList();
    return htmx.process(document.body);
  };

}).call(this);


//# sourceMappingURL=gpt-admin-js.js.map
//# sourceURL=coffeescript