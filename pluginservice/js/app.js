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
    // create HTML element for the topic, including a data-api-uri attribute
    // which enables scrolling back to it via the "back" button in the topic
    // display
    var html = $(
      '<div data-role="topic-box" class="panel panel-info" ' +
      'data-api-uri="' + result.api_uri + '">' +
      '<div class="panel-body">' +
      '<h2>' +
      result.label +
      '</h2>' +
      '<p>' + result.description + '</p>' +
      '</div>' +
      '</div>'
    );

    // a click loads the topic display
    html.on('click', function (e) {
      e.preventDefault();
      that.trigger('results:load-topic', result.api_uri);
    });

    html.fadeIn();

    topicBoxContainerElement.append(html);
  };

  // returns true if 1 or more topics have been loaded into the container
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

  // scroll to the search result which has a topic URI corresponding to
  // topicUri; NB each search result HTML element is given a data-api-uri
  // attribute when created to facilitate this
  that.scrollTo = function (topicUri) {
    var body = $(document.body);

    var offsets = element.find('[data-api-uri="' + topicUri + '"]').offset()

    // the scroll from the top is the offset of the element minus the padding
    // on the top of the body
    var bodyPadding = parseInt(body.css('padding-top'));
    var top = offsets.top - bodyPadding;

    body.animate({scrollTop: top}, {duration: 0});
  };

  return that;
};

var TopicPanel = function (selector) {
  var that = $({});

  var currentTopicUri = null;

  var element = $(selector);

  var topicLoading = element.find('[data-role=topic-loading]');

  var topicDisplay = element.find('[data-role=topic-display]');
  var topicHeading = element.find('[data-role=topic-heading]');
  var topicDescription = element.find('[data-role=topic-description]');
  var topicPlayers = element.find('[data-role=topic-players]');
  var topicContent = element.find('[data-role=topic-content]');
  var topicPages = element.find('[data-role=topic-pages]');

  var backButton = element.find('[data-role=back-to-search-button]');

  backButton.on('click', function () {
    that.trigger('topic:back-to-search', currentTopicUri);
  });

  // returns true if uri looks like it might be an image file
  var isImage = function (uri) {
    return uri && (uri.endsWith('.jpg') || uri.endsWith('.JPG') ||
           uri.endsWith('.jpeg') || uri.endsWith('.JPEG') ||
           uri.endsWith('.png') || uri.endsWith('.PNG') ||
           uri.endsWith('.gif') || uri.endsWith('.GIF') ||
           uri.endsWith('.bmp') || uri.endsWith('.BMP'));
  };

  // populate players, content or pages list and show the section if it
  // has any elements in its list;
  // if webpages is true, no thumbnail is shown
  var populateSection = function (dataRole, items, webpages) {
    webpages = !!webpages;

    var section = element.find('[data-role=' + dataRole + ']');

    if (items.length > 0) {
      var list = section.find('[data-role=' + dataRole + '-list]');
      list.empty();

      for (var i = 0; i < items.length; i++) {
        var listItem = '<div data-role="topic-media" class="panel panel-info">';

        // thumbnail
        if (!webpages) {
          listItem += '<img data-role="topic-media-thumb" src="';

          if (items[i].thumbnail) {
            listItem += items[i].thumbnail;
          }
          else if (isImage(items[i].uri)) {
            // if this looks like an image, try to load it
            listItem += items[i].uri;
          }
          else {
            // TODO generic thumbnail
          }

          listItem += '"> ';
        }

        // label
        listItem += '<span data-role="topic-media-label">';
        listItem += items[i].label;

        // link to preview
        var uri = items[i].uri;

        // extract the domain to give a hint about the source
        var domainRegex = new RegExp('https?:\/\/([^\/]+)');
        var matches = domainRegex.exec(uri);
        var domain = (matches ? matches[1] : 'open');

        listItem += ' [<a href="' + uri + '" target="_blank">' + domain + '</a>]';

        listItem += '</span>';

        // select button
        listItem += '<button data-role="topic-media-select-button" class="btn btn-default">Select</button>';

        list.append(listItem);
      }

      section.removeClass('ui-inactive');
      section.addClass('ui-active');
    }
    else {
      section.removeClass('ui-active');
      section.addClass('ui-inactive');
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

  that.topicLoading = function (bool) {
    if (bool) {
      topicLoading.removeClass('ui-inactive');
      topicLoading.addClass('ui-active');

      topicDisplay.removeClass('ui-active');
      topicDisplay.addClass('ui-inactive');
    }
    else {
      topicLoading.removeClass('ui-active');
      topicLoading.addClass('ui-inactive');

      topicDisplay.removeClass('ui-inactive');
      topicDisplay.addClass('ui-active');
    }
  };

  that.loadTopic = function (content) {
    var i;

    currentTopicUri = content.apiUri;

    topicHeading.text(content.label);

    if (content.description) {
      topicDescription.text(content.description);
      topicDescription.removeClass('ui-inactive');
      topicDescription.addClass('ui-active');
    }
    else {
      topicDescription.removeClass('ui-active');
      topicDescription.addClass('ui-inactive');
    }

    // show media
    populateSection('topic-players', content.players);
    populateSection('topic-content', content.content);
    populateSection('topic-pages', content.pages, true);
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
        content.apiUri = url;
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

  that.topic = function (topicUri) {
    console.log('loading topic ' + topicUri);

    $.ajax({
      url: topicUri,

      dataType: 'json',

      success: function (content) {
        content.apiUri = topicUri;
        that.trigger('client:topic', content);
      },

      error: function (err) {
        that.trigger('client:error', err);
      }
    });
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
    topicPanel.topicLoading(true);

    // load the topic
    client.topic(topicUri);
  });

  topicPanel.on('topic:back-to-search', function (e, topicUri) {
    // hide the topic panel, show the search panel
    topicPanel.setInactive();
    searchResultsPanel.setActive();

    // scroll to the indicated search result
    searchResultsPanel.scrollTo(topicUri);
  });

  // handler for returned topic
  client.on('client:topic', function (e, content) {
    // hide the search panel, show the topic panel
    topicPanel.setActive();
    searchResultsPanel.setInactive();

    // load topic data into topic panel
    topicPanel.loadTopic(content);

    // hide topic loading message
    topicPanel.topicLoading(false);
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
