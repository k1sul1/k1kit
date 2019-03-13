import React, { Component, PureComponent, Fragment } from 'react'
import { FixedSizeList as List } from 'react-window'
import InfiniteLoader from 'react-window-infinite-loader'

const LOADING = 1;
const LOADED = 2;
let itemStatusMap = {};

const isItemLoaded = index => !!itemStatusMap[index];
const loadMoreItems = (startIndex, stopIndex) => {
  for (let index = startIndex; index <= stopIndex; index++) {
    itemStatusMap[index] = LOADING;
  }
  return new Promise(resolve =>
    setTimeout(() => {
      for (let index = startIndex; index <= stopIndex; index++) {
        itemStatusMap[index] = LOADED;
      }
      resolve();
    }, 2500)
  );
};

class Row extends PureComponent {
  render() {
    const { index, style } = this.props;

    return (
      <div className="ListItem" style={style}>
        {itemStatusMap[index] === LOADED ? (
          <Fragment>
            <p>Transient {index}</p>
            <button className="button button-delete-link">Delete</button>
          </Fragment>
        ) : (
          <p>Loading...</p>
        )}
      </div>
    );
  }
}

export default class Transients extends Component {
  ref = React.createRef()

  state = {
    width: window.innerWidth - 100,
    height: window.innerHeight - 100,
  }

  setListHeight = () => {
    const container = this.ref && this.ref.current

    if(container) {
      const { width, height } = container.getBoundingClientRect()
      this.setState({
        width: Math.round(width),
        height: Math.round(height),
      })
    }
  }

  componentDidMount() {
    this.setListHeight()

    window.addEventListener('resize', this.setListHeight)
  }

  componentWillUnmount() {
    window.removeEventListener('resize', this.setListHeight)
  }

  render() {
    const { width, height } = this.state

    return (
      <div ref={this.ref}>
        <InfiniteLoader
          isItemLoaded={isItemLoaded}
          itemCount={1000}
          loadMoreItems={loadMoreItems}
        >
          {({ onItemsRendered, ref }) => (
            <List
              className="List"
              height={height}
              itemCount={1000}
              itemSize={30}
              onItemsRendered={onItemsRendered}
              ref={ref}
              width={width}
            >
              {Row}
            </List>
          )}
        </InfiniteLoader>
      </div>
    )
  }
}
