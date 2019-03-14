import React, { Component, Fragment } from 'react'
import { isError } from 'lodash'
import { Circle } from 'rc-progress'

import http from '../lib/http'
import { isProduction } from '../lib/env'

export default class Resolver extends Component {
  state = {
    indexStatus: null,
    waitingResponse: false,
  }

  interval = null

  async getIndexStatus() {
    const indexStatus = await http('/wp-json/k1/v1/resolver/index')

    if (isError(indexStatus)) {
      throw indexStatus
    }

    this.setState({
      indexStatus,
    })
  }

  async rebuildIndex() {
    const response = await http('/wp-json/k1/v1/resolver/index/build', {
      method: 'POST',
      // body: JSON.stringify({}),
    })

    console.log(response)

  }

  async componentDidMount() {
    if (isProduction()) {
      this.interval = setInterval(() => this.getIndexStatus(), 1000);
    }

    this.getIndexStatus()
  }

  componentWillUnmount() {
    if (this.interval) {
      clearInterval(this.interval)
    }
  }

  render() {
    const { indexStatus } = this.state

    if(indexStatus === null) {
      return <p>Loading...</p>
    }

    const { indexing, indexed, total, percentage } = indexStatus

    const colour = '#46b450'
    return (
      <div>
        {indexing ? (
          <Fragment>
            <h2>Indexing in progress</h2>
            <p>Indexes will be created for {total} permalinks</p>
          </Fragment>
        ) : (
          <Fragment>
            <h2>Index</h2>
            <p>
              Index contains {indexed} permalinks. 
              Total count of indexable permalinks is {total}.
            </p>

            <p>
              If you want to recreate the index, you may do so at any point, the plugin will 
              continue to work normally while the index is recreating.
            </p>

            <button className="button button-link-delete" onClick={() => this.rebuildIndex()}>
              Rebuild index
            </button>
          </Fragment>
        )}

        <div className="circle-progress">
          <Circle className="progress" percent={percentage} strokeWidth="1" strokeColor={colour} />
          <div className="text">
            <span className="percentage">
              {Math.floor(percentage)}%
            </span>

            <span className="numeric">
              {indexed}/{total}
            </span>
          </div>
        </div>
      </div>
    )
  }
}
