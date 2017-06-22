// endpoint is the API endpoint on the pluginservice which proxies for RES
// proper
window.App = function (endpoint, callbackUrl) {
  var that = {
    init: function () {
      var searchForm = SearchForm('#search-form');
      var searchResultsPanel = SearchResultsPanel('#search-results-panel');
      var topicPanel = TopicPanel('#topic-panel', callbackUrl);
      var client = RESClient(endpoint, callbackUrl);
      var eventCoordinator = EventCoordinator(
        searchForm,
        searchResultsPanel,
        topicPanel,
        client
      );
    }
  };

  return that;
};

var SearchForm = function (selector) {
  var that = $({});

  var element = $(selector);
  var input = element.find('[data-role=search-input]');
  var mediaFilter = element.find('[data-role=search-media-filter]');
  var button = element.find('[data-role=search-button]');

  button.on('click', function (e) {
    e.preventDefault();

    var params = {
        query: input.val(),
        media: mediaFilter.find('option:selected').val()
    };

    that.trigger('search:send', params);
  });

  // disable the button and search input
  that.disable = function () {
    input.attr('disabled', 'disabled');
    button.attr('disabled', 'disabled');
    mediaFilter.attr('disabled', 'disabled');
  };

  // enable button and input
  that.enable = function () {
    input.removeAttr('disabled');
    button.removeAttr('disabled');
    mediaFilter.removeAttr('disabled');
  };

  return that;
};

var SearchResultsPanel = function (selector) {
  var that = $({});

  var element = $(selector);
  var noSearchYetElement = element.find('[data-role=no-search-yet]');
  var searchInProgressElement = element.find('[data-role=search-in-progress]');
  var topicBoxContainerElement = element.find('[data-role=result-box-container]');
  var loadMoreButton = element.find('[data-role=load-more-button]');

  loadMoreButton.on('click', function () {
    that.trigger('results:more');
  });

  // saved scroll position, for movement back to search results from topic
  var savedScrollPosition = 0;

  // save the current scroll position, so search results can be scrolled
  // back to it when returning to search results from the topic panel
  that.saveScrollPosition = function () {
    savedScrollPosition = $(document.body).scrollTop();
  };

  // return to the last-saved scroll position (without animation)
  that.returnToSavedScrollPosition = function () {
    that.scrollToPosition(savedScrollPosition, 0);
  };

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
      // we've done a search, so hide the "please do a search" message
      noSearchYetElement.removeClass('ui-active');
      noSearchYetElement.addClass('ui-inactive');

      // hide the load more button
      loadMoreButton.removeClass('ui-active');
      loadMoreButton.addClass('ui-inactive');

      // show search in progress
      searchInProgressElement.removeClass('ui-inactive');
      searchInProgressElement.addClass('ui-active');
    }
    else {
      // hide search in progress
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
      '<div data-role="result-box" class="panel panel-info" ' +
      'data-api-uri="' + result.api_uri + '">' +
      '<div class="panel-body">' +
      '<h2>' +
      result.label +
      '</h2>' +
      '<p data-role="result-box-description">' + result.description + '</p>' +
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

  // returns true if one or more topics have been loaded into the container
  that.hasResults = function () {
    var topicBoxes = topicBoxContainerElement.find('[data-role=result-box]');
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
    // area if appropriate; otherwise show the results
    if (that.hasResults()) {
      that.setSearchResults();

      // scroll to the first result with animation
      console.log('will scroll to ' + results.items[0].api_uri);
      that.scrollTo(results.items[0].api_uri, 500);
    }
    else {
      that.setSearchNoResults();
    }
  };

  // scroll to the search result which has a topic URI corresponding to
  // topicUri; NB each search result HTML element is given a data-api-uri
  // attribute when created to facilitate this
  that.scrollTo = function (topicUri, duration) {
    var body = $(document.body);

    var offsets = element.find('[data-api-uri="' + topicUri + '"]').offset()

    // the scroll from the top is the offset of the element minus the padding
    // on the top of the body
    var bodyPadding = parseInt(body.css('padding-top'));
    var top = offsets.top - bodyPadding;

    that.scrollToPosition(top, duration);
  };

  that.scrollToPosition = function (top, duration) {
    var body = $(document.body);
    body.animate({scrollTop: top}, {duration: duration});
  };

  return that;
};

// callbackUrl: if set, "Select" buttons are shown for selecting media objects
var TopicPanel = function (selector, callbackUrl) {
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

  var backButton = element.find('[data-role=topic-back-to-search-button]');

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
        var item = items[i];

        var listItem = '<div data-role="topic-media" class="panel panel-info">';

        // thumbnail
        if (!webpages && (item.thumbnail || isImage(item.uri))) {
          listItem += '<div data-role="topic-media-thumb-container">' +
                      '<img data-role="topic-media-thumb" src="';

          if (item.thumbnail) {
            listItem += item.thumbnail;
          }
          else if (isImage(item.uri)) {
            // if this looks like an image, try to load it like a thumbnail
            listItem += item.uri;
          }

          listItem += '"></div>';
        }

        // label
        listItem += '<div data-role="topic-media-label">';
        listItem += item.label;

        // extract the domain to give a hint about the source for the media
        var uri = item.uri;
        var domainRegex = new RegExp('https?:\/\/([^\/]+)');
        var matches = domainRegex.exec(uri);
        var domain = (matches ? matches[1] : 'preview');

        // link to preview with media type if available
        listItem += ' [';

        if (item.mediaType) {
          listItem += item.mediaType + ' from ';
        }

        listItem += '<a href="' + uri + '" target="_blank">' +
                    domain +
                    '</a>]';

        listItem += '</div>';

        listItem = $(listItem);

        // select button, only if callbackUrl is specified
        if (callbackUrl) {
          var btn = $(
            '<div data-role="topic-media-select-button">' +
            '<button class="btn btn-default">Select</button>' +
            '</div>'
          );
          listItem.append(btn);

          // work-around for adding handlers to UI elements inside a loop
          var handler = (function (item) {
            return function () {
              that.trigger('topic:select', item);
            }
          })(item);

          btn.on('click', handler);
        }

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
    // already have this topic loaded
    if (currentTopicUri === content.apiUri) {
      return;
    }

    currentTopicUri = content.apiUri;

    // heading (label for the topic)
    topicHeading.text(content.label);

    // description (of the topic)
    if (content.description) {
      topicDescription.text(content.description);
      topicDescription.removeClass('ui-inactive');
      topicDescription.addClass('ui-active');
    }
    else {
      topicDescription.removeClass('ui-active');
      topicDescription.addClass('ui-inactive');
    }

    // media
    populateSection('topic-players', content.players);
    populateSection('topic-content', content.content);
    populateSection('topic-pages', content.pages, true);
  };

  /*
   * Forward the browser to the callback URL with the item data encoded into
   * its querystring
   *
   * mediaObj comes from the pluginservice API; see plugin/index.php,
   * extractMedia() function for how this is put together by the API
   *
   * It will look like one of the following:
   *
   * {
   *   'uri' : '<media URI>',
   *   'source_uri' : '<acropolis URI>,
   *   'label' : '<label>',
   *   'description' : '<description>',
   *   'thumbnail' : '<thumbnail URI>',
   *   'height_px' : <height>,
   *   'width_px' : <width>,
   *   'date' : 'YYYY-MM-DD',
   *   'location' : '<instance URI>'
   * }
   *
   * or
   *
   * {
   *   'uri' : '<media URI>',
   *   'source_uri' : '<acropolis URI>,
   *   'label' : '<label>',
   *   'description' : '<description>'
   * }
   *
   * The redirect goes to <callbackUrl>?repo_id=<repo ID>&media=<JSON-encoded media object>
   */
  that.forward = function (mediaObj) {
    // encode mediaObj into the querystring
    var mediaJson = JSON.stringify(mediaObj);
    var fullCallbackUrl = URI(callbackUrl).query({media: mediaJson});

    // forward to full callbackUrl
    window.location.href = fullCallbackUrl.toString();
  };

  return that;
};

// get data from Acropolis through the pluginservice proxy, which
// converts it into friendly JSON
var RESClient = function (endpoint, callbackUrl) {
  var that = $({});

  var offset = 0;
  var limit = 10;
  var lastQuery = null;
  var lastMedia = null;

  that.reset = function () {
    offset = 0;
  };

  that.setOffset = function (newOffset) {
    offset = newOffset;
  };

  that.search = function (params) {
    var query = params.query;
    var media = params.media;

    lastQuery = query;
    lastMedia = media;

    var url = endpoint + 'search?q=' + encodeURIComponent(query) +
      '&offset=' + offset + '&media=' + media;

    console.log('search URL: ' + url);

    $.ajax({
      url: url,

      dataType: 'json',

      success: function (content) {
        // append the apiUri to the content item
        content.apiUri = url;
        that.trigger('client:results', content);
      },

      error: function (err) {
        that.trigger('client:error', err);
      }
    });
  };

  that.more = function () {
    if (!(lastQuery && lastMedia)) {
      return;
    }

    offset += limit;
    that.search({query: lastQuery, media: lastMedia});
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

var EventCoordinator = function (searchForm, searchResultsPanel, topicPanel, client, app) {
  // handler for clicks on the search button
  searchForm.on('search:send', function (e, params) {
    // return if there's no search term
    if (params.query === '') {
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
    client.search(params);
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
    // save the scroll position of the search panel
    searchResultsPanel.saveScrollPosition();

    // hide the search panel, show the topic panel
    topicPanel.setActive();
    searchResultsPanel.setInactive();

    // show topic loading message
    topicPanel.topicLoading(true);

    // load the topic
    client.topic(topicUri);
  });

  // handler for "back to search" button on topic panel
  topicPanel.on('topic:back-to-search', function (e, topicUri) {
    // hide the topic panel, show the search panel
    topicPanel.setInactive();
    searchResultsPanel.setActive();

    // scroll to the last saved scroll position on the search panel
    searchResultsPanel.returnToSavedScrollPosition();
  });

  // handler for topic selected - forward back to Moodle
  topicPanel.on('topic:select', function (e, mediaObj) {
    topicPanel.forward(mediaObj);
  });

  // handler for topic returned from Acropolis
  client.on('client:topic', function (e, content) {
    // hide the search panel, show the topic panel
    topicPanel.setActive();
    searchResultsPanel.setInactive();

    // load topic data into topic panel
    topicPanel.loadTopic(content);

    // hide topic loading message
    topicPanel.topicLoading(false);
  });

  // handler for search results returned from Acropolis
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

  // handler for client errors
  client.on('client:error', function (e, err) {
    console.error('Error while sending request');
    console.error(err);
  });
};
