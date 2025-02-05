<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Wizard</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Chat Wizard</h1>
    <form id="chatWizardForm">
        <div>
            <label for="systemPrompt">System Prompt:</label>
            <textarea id="systemPrompt" name="systemPrompt" rows="3" cols="50"></textarea>
        </div>
        <div id="messageRows">
            <div>
                <label for="userMessage1">User Message 1:</label>
                <input type="text" id="userMessage1" name="userMessage1">
                <label for="assistantMessage1">Assistant Message 1:</label>
                <input type="text" id="assistantMessage1" name="assistantMessage1">
            </div>
        </div>
        <button type="button" id="addRowButton">Add Row</button>
        <div>
            <label for="n">n:</label>
            <input type="number" id="n" name="n" value="1">
        </div>
        <div>
            <label for="maxTokens">Max Tokens:</label>
            <input type="number" id="maxTokens" name="maxTokens" value="4">
        </div>
        <div>
            <label for="temperature">Temperature:</label>
            <input type="number" step="0.1" id="temperature" name="temperature" value="1.0">
        </div>
        <div>
            <label for="model">Model:</label>
            <select id="model" name="model">
                <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                <option value="gpt-4o">GPT-4o</option>
                <option value="gpt-4o-mini">GPT-4o-mini</option>
            </select>
        </div>
        <div>
            <label for="userMessage">User Message:</label>
            <input type="text" id="userMessage" name="userMessage">
        </div>
        <div>
            <label for="userMessage">PW for this wizzard:</label>
            <input type="password" id="wizzardpw" name="wizzardpw">
        </div>
        <button type="submit">Submit</button>

        <button type="button" id="exportJsonButton">Export to JSON</button>
        <button type="button" id="importJsonButton">Import from JSON</button>
        <input type="file" id="jsonFileInput" style="display: none;">

    </form>
    <div id="gpt"></div>

    <script>
        $(document).ready(function() {
            let messageCount = 1;

            $('#addRowButton').click(function() {
                messageCount++;
                $('#messageRows').append(`
                    <div>
                        <label for="userMessage${messageCount}">User Message ${messageCount}:</label>
                        <input type="text" id="userMessage${messageCount}" name="userMessage${messageCount}">
                        <label for="assistantMessage${messageCount}">Assistant Message ${messageCount}:</label>
                        <input type="text" id="assistantMessage${messageCount}" name="assistantMessage${messageCount}">
                    </div>
                `);
            });

            $('#chatWizardForm').submit(function(event) {
                event.preventDefault();

                const messages = [
                    { role: 'system', content: $('#systemPrompt').val() }
                ];

                for (let i = 1; i <= messageCount; i++) {
                    const userMessage = $(`#userMessage${i}`).val();
                    const assistantMessage = $(`#assistantMessage${i}`).val();
                    if (userMessage) messages.push({ role: 'user', content: userMessage });
                    if (assistantMessage) messages.push({ role: 'assistant', content: assistantMessage });
                  }
                
                var userMessage = $(`#userMessage`).val();
                messages.push({ role: 'user', content: userMessage });

                const data = {
                    secret: $('#wizzardpw').val(), // Replace with your actual secret key
                    messages: JSON.stringify(messages),
                    n: $('#n').val(),
                    max_tokens: $('#maxTokens').val(),
                    temperature: $('#temperature').val(),
                    model: $('#model').val(),
                    user_message: $('#userMessage').val()
                };

                $.ajax({
                    url: 'https://ed-tech.app/py-api/chat-wizard',
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        processGptData(response, 'prompt_id');
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        $('body').append(`AJAX Error: ${textStatus}`);
                    }
                });
            });
            $('#exportJsonButton').click(function() {
                const formData = {
                    systemPrompt: $('#systemPrompt').val(),
                    messages: []
                };

                for (let i = 1; i <= messageCount; i++) {
                    formData.messages.push({
                        userMessage: $(`#userMessage${i}`).val(),
                        assistantMessage: $(`#assistantMessage${i}`).val()
                    });
                }

                formData.n = $('#n').val();
                formData.maxTokens = $('#maxTokens').val();
                formData.temperature = $('#temperature').val();
                formData.model = $('#model').val();
                formData.userMessage = $('#userMessage').val();

                const json = JSON.stringify(formData, null, 2);
                const blob = new Blob([json], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'chatWizardData.json';
                a.click();
                URL.revokeObjectURL(url);
            });

            $('#importJsonButton').click(function() {
                $('#jsonFileInput').click();
            });

            $('#jsonFileInput').change(function(event) {
                const file = event.target.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const json = e.target.result;
                    const formData = JSON.parse(json);

                    $('#systemPrompt').val(formData.systemPrompt);
                    $('#messageRows').empty();
                    messageCount = 0;

                    formData.messages.forEach((message, index) => {
                        messageCount++;
                        $('#messageRows').append(`
                            <div>
                                <label for="userMessage${messageCount}">User Message ${messageCount}:</label>
                                <input type="text" id="userMessage${messageCount}" name="userMessage${messageCount}" value="${message.userMessage}">
                                <label for="assistantMessage${messageCount}">Assistant Message ${messageCount}:</label>
                                <input type="text" id="assistantMessage${messageCount}" name="assistantMessage${messageCount}" value="${message.assistantMessage}">
                            </div>
                        `);
                    });

                    $('#n').val(formData.n);
                    $('#maxTokens').val(formData.maxTokens);
                    $('#temperature').val(formData.temperature);
                    $('#model').val(formData.model);
                    $('#userMessage').val(formData.userMessage);
                };
                reader.readAsText(file);
            });
        });

        

        function processGptData(gptData, prompt_id) {
            var adjustedP, combinedData, combinedScores, combinedScoresValues, count, counts, first_word, gptDiv, i, item, j, k, l, labels, len, len1, name, name1, p, pValueSum, pValues, r, responseCount, rest_of_sentence, scaledPValues, sigmoid, sortedCombinedScoresValues, sortedData, sortedLabels, uniqueResponses, words;
            // $.ajax("/2024/gpt/api.php?class=send_prompts&saveGptJson=" + prompt_id, {
            //     type: "POST",
            //     data: {
            //         gpt: gptData
            //     },
            //     error: function(jqXHR, textStatus, errorThrown) {
            //         return $('body').append(`AJAX Error: ${textStatus}`);
            //     },
            //     success: function(data, textStatus, jqXHR) {}
            // });
            sortedData = [];

            // Extract and sort data based on p-values
            for (i = j = 1; j <= $('#n').val(); i = ++j) {
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

                // Limit extreme negative p-values and apply Sigmoid function
                adjustedP = sigmoid(item.p);
                responseCount[item.r] += 1;
                pValueSum[item.r] += adjustedP;
            }
            // Append sorted data to div#gpt
            gptDiv = $('#gpt');
            gptDiv.html("<h3>GPT</h3>"); // Clear the div first
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
        }
    </script>
</body>
</html>
