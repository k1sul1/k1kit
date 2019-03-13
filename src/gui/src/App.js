import React, { Component } from 'react'
import { HashRouter as Router, Route } from "react-router-dom"

import { Main, Transients, Resolver } from './containers'
import Navigation from './components/Navigation'

import './App.scss'

class App extends Component {
  state = {
    error: null,
  }

  componentDidCatch(error, info) {
    console.log('Something is wrong.', error, info)

    this.setState({
      error,
    })
  }

  render() {
    const { error } = this.state

    return (
      <Router>
        <div className="App">
          <Navigation />

          {error === null ? (
            <div>
              <Route exact path="/" component={Main} />
              <Route path="/transients" component={Transients} />
              <Route path="/resolver" component={Resolver} />
            </div>
          ) : (
            <div>
              <p>Blasted! Something broke.</p>
              <p>{JSON.stringify(error, null, 2)}</p>

              <button onClick={() => this.setState({ error: null })}>
                I don't care, try again
              </button>
            </div>
          )}
        </div>
      </Router>
    )
  }
}

export default App
