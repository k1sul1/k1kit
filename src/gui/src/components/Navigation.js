import React, { Component } from 'react'
import { NavLink } from "react-router-dom"

export default class Navigation extends Component {
  render() {
    return (
      <header>
        <NavLink to="/" exact>
          General
        </NavLink>

        <NavLink to="/transients">
          Transients
        </NavLink>

        <NavLink to="/resolver">
          Resolver
        </NavLink>
      </header>
    )
  }
}
