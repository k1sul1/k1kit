import { isProduction } from './env'

let nonce = sessionStorage.getItem('nonce')

async function getNonce() {

  if(isProduction()) {
    return window.k1kit.nonce
  } else if (!nonce) {
    const response = await prompt('This totally isn\'t a phishing attempt, can you paste me the nonce from WP?')
    nonce = response
  }

  sessionStorage.setItem('nonce', nonce)
  return nonce
}

export default async function http(url, opts = {}) {
  const nonce = await getNonce()
  const options = {
    mode: 'cors',
    method: 'GET',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce
    },
    ...opts,
  }

  if (options.method.toLowerCase() === 'post') {
    options.body = JSON.stringify({
      ...(options.body || {}),
    })
  }

  try {
    const response = await fetch(url, options)
    const json = await response.json()

    return json
  } catch (e) {
    console.error('Request error', e)
    return e // Handle in component
  }
}

window.http = http