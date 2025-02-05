from flask import Flask, request, jsonify
from flask_cors import CORS
from openai import OpenAI
client = OpenAI()
import os
import json

app = Flask(__name__)
CORS(app)

# Setzen Sie Ihren OpenAI API-Schlüssel hier
# openai.api_key = os.getenv('OPENAI_API_KEY_NOGIT')

SECRET_KEY = os.getenv('SECRET_KEY')

@app.route('/py-api/ping', methods=['GET'])
def ping():
  return "pong"


@app.route('/py-api/chat-wizard', methods=['POST'])
def chatWizard():
  data = request.form
  if data.get('secret') != SECRET_KEY:
    return jsonify({"error": "Unauthorized"}), 401
    
  try:
    messages = json.loads(data.get('messages'))
  except (TypeError, json.JSONDecodeError) as e:
    return jsonify({"error": "Invalid JSON format"}), 400
  
  n = int(data.get('n', 1))  # Default to 1 if not provided
  max_tokens = int(data.get('max_tokens', 4))  # Default to 4 if not provided
  temperature = float(data.get('temperature', 1.0))  # Default to 1.0 if not provided
  model = data.get('model', 'gpt-3.5-turbo')  # Default to 'gpt-3.5-turbo' if not provided

  #  if model not in ['gpt-3.5-turbo', 'gpt-4o']:
  #    return jsonify({"error": "Invalid model"}), 400

  response = client.chat.completions.create(
    model=model,
    n=n,
    max_tokens=max_tokens,
    temperature=temperature,
    logprobs=True,
    messages=messages
  )
  response_dict = response.to_dict()
  #return jsonify({"response": chat_response, "all": response_dict})
  result = {}
  for i, choice in enumerate(response_dict['choices']):
    index = i + 1
    result[f"r{index}"] = choice['message']['content']
    result[f"p{index}"] = choice['logprobs']['content'][0]['logprob'] if 'logprobs' in choice and 'content' in choice['logprobs'] and choice['logprobs']['content'] else None
  result["usage"] = response_dict['usage'] if 'usage' in response_dict else None
  # Return the response and all details
  # return jsonify(result)
  return app.response_class(
    response=json.dumps(result, ensure_ascii=False).encode('utf-8'),
    mimetype='application/json'
  )


if __name__ == '__main__':
#  app.run(host='0.0.0.0', port=3002)
  from waitress import serve
  serve(app, host="0.0.0.0", port=3002)
