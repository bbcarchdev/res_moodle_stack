// endpoint is the API endpoint on the pluginservice which proxies for RES
// proper
window.App = function (endpoint) {
  return {
    init: function () {
      var searchForm = SearchForm('#search-form');
      var searchResultsPanel = SearchResultsPanel('#search-results-panel');
      var topicPanel = TopicPanel('#topic-panel');
      var client = RESClient(endpoint);
      var eventCoordinator = EventCoordinator(
        searchForm,
        searchResultsPanel,
        topicPanel,
        client
      );
    }
  };
};

var SearchForm = function (selector) {
  var that = $({});

  var element = $(selector);
  var input = element.find('[data-role=query-input]');
  var button = element.find('[data-role=search-button]');

  button.on('click', function (e) {
    e.preventDefault();
    that.trigger('search:send', input.val());
  });

  // disable the button and search input
  that.disable = function () {
    input.attr('disabled', 'disabled');
    button.attr('disabled', 'disabled');
  };

  // enable button and input
  that.enable = function () {
    input.removeAttr('disabled');
    button.removeAttr('disabled');
  };

  return that;
};

var SearchResultsPanel = function (selector) {
  var that = $({});

  var element = $(selector);
  var noSearchYetElement = element.find('[data-role=no-search-yet]');
  var searchInProgressElement = element.find('[data-role=search-in-progress]');
  var topicBoxContainerElement = element.find('[data-role=topic-box-container]');
  var loadMoreButton = element.find('[data-role=load-more-button]');

  loadMoreButton.on('click', function () {
    that.trigger('results:more');
  });

  // clear search results and hide the search results heading
  that.clear = function () {
    topicBoxContainerElement.empty();
    disableAllDivsExcept('');
  };

  // disable all the divs inside the container except the one
  // whose data-role attribute matches dataRole
  var disableAllDivsExcept = function (dataRole) {
    element.children('div').each(function () {
      elt = $(this);

      if (elt.attr('data-role') === dataRole) {
        elt.removeClass('ui-inactive');
        elt.addClass('ui-active');
      }
      else {
        elt.removeClass('ui-active');
        elt.addClass('ui-inactive');
      }
    });
  };

  that.setNoSearchYet = function () {
    disableAllDivsExcept('no-search-yet');
  };

  that.setSearchNoResults = function () {
    disableAllDivsExcept('no-search-results');
  };

  that.setSearchResults = function () {
    disableAllDivsExcept('search-results');
  };

  that.searchInProgress = function (bool) {
    if (bool) {
      // we've done a search, so we can hide the "please do a search" message
      noSearchYetElement.removeClass('ui-active');
      noSearchYetElement.addClass('ui-inactive');

      // hide the load more button
      loadMoreButton.removeClass('ui-active');
      loadMoreButton.addClass('ui-inactive');

      searchInProgressElement.removeClass('ui-inactive');
      searchInProgressElement.addClass('ui-active');
    }
    else {
      searchInProgressElement.removeClass('ui-active');
      searchInProgressElement.addClass('ui-inactive');
    }
  };

  that.setActive = function () {
    element.removeClass('ui-inactive');
    element.addClass('ui-active');
  };

  that.setInactive = function () {
    element.removeClass('ui-active');
    element.addClass('ui-inactive');
  };

  that.loadResult = function (result) {
    var html = $(
      '<div data-role="topic-box" class="panel panel-info">' +
      '<div class="panel-body">' +
      '<h2>' +
      result.label +
      '</h2>' +
      '<p>' + result.description + '</p>' +
      '</div>' +
      '</div>'
    );

    // a click on the label link loads the topic
    html.on('click', function (e) {
      e.preventDefault();
      that.trigger('results:load-topic', result.api_uri);
    });

    html.fadeIn();

    topicBoxContainerElement.append(html);
  };

  that.hasResults = function () {
    var topicBoxes = topicBoxContainerElement.find('[data-role=topic-box]');
    return topicBoxes.length > 0;
  };

  that.loadResults = function (results) {
    // add the results (if any) to the container
    for (var i = 0; i < results.items.length; i++) {
      that.loadResult(results.items[i]);
    }

    // if we've got more results available, show the "Load more" button
    if (results.hasNext) {
      loadMoreButton.removeClass('ui-inactive');
      loadMoreButton.addClass('ui-active');
    }
    else {
      loadMoreButton.removeClass('ui-active');
      loadMoreButton.addClass('ui-inactive');
    }

    // count the number of results in the container and turn on "no results"
    // area if appropriate; otherwise show the results area
    if (that.hasResults()) {
      that.setSearchResults();
    }
    else {
      that.setSearchNoResults();
    }
  };

  return that;
};

var TopicPanel = function (selector) {
  var that = $({});

  var element = $(selector);

  that.setActive = function () {
    element.removeClass('ui-inactive');
    element.addClass('ui-active');
  };

  that.setInactive = function () {
    element.removeClass('ui-active');
    element.addClass('ui-inactive');
  };

  return that;
};

var RESClient = function (endpoint) {
  var that = $({});

  var offset = 0;
  var limit = 10;
  var lastQuery = null;

  that.reset = function () {
    offset = 0;
  };

  that.setOffset = function (newOffset) {
    offset = newOffset;
  };

  that.search = function (query) {
    lastQuery = query;

    var url = endpoint + '?q=' + encodeURIComponent(query) +
      '&offset=' + offset;

    console.log('search URL: ' + url);

    $.ajax({
      url: url,

      dataType: 'json',

      success: function (content) {
        that.trigger('client:results', content);
      },

      error: function (err) {
        that.trigger('client:error', err);
      }
    });
  };

  that.more = function () {
    if (!lastQuery) {
      return;
    }

    offset += limit;
    that.search(lastQuery);
  };

  return that;
};

var EventCoordinator = function (searchForm, searchResultsPanel, topicPanel, client) {
  // handler for clicks on the search button
  searchForm.on('search:send', function (e, query) {
    // return if there's no search term
    if (query === '') {
      return;
    }

    // disable the search form until search completes
    searchForm.disable();

    // hide the topic panel, show the search panel
    topicPanel.setInactive();
    searchResultsPanel.setActive();

    // clear the search panel and mark as "waiting"
    searchResultsPanel.clear();
    searchResultsPanel.searchInProgress(true);

    // reset the client's paging
    client.reset();

    // perform the search
    client.search(query);
  });

  // handler for clicks on the "load more" button
  searchResultsPanel.on('results:more', function (e) {
    // disable the search form until search completes
    searchForm.disable();

    // hide the topic panel, show the search panel
    topicPanel.setInactive();
    searchResultsPanel.setActive();

    // mark search in progress but don't clear panel
    searchResultsPanel.searchInProgress(true);

    // perform the search
    client.more();
  });

  // handler for clicks on topics
  searchResultsPanel.on('results:load-topic', function (e, topicUri) {
    // hide the search panel, show the topic panel
    topicPanel.setActive();
    searchResultsPanel.setInactive();

    // show topic loading message

  });

  // handler for returned topic
  client.on('client:topic', function (e, content) {
    // hide the search panel, show the topic panel
    topicPanel.setActive();
    searchResultsPanel.setInactive();

    // hide topic loading message


    // load topic data into topic panel
  });

  // handler for returned search results
  client.on('client:results', function (e, content) {
    // re-enable the search form
    searchForm.enable();

    // hide the topic panel, show the search panel
    topicPanel.setInactive();
    searchResultsPanel.setActive();

    // turn off search in progress
    searchResultsPanel.searchInProgress(false);

    // load results into the search results panel
    searchResultsPanel.loadResults(content);
  });
};
