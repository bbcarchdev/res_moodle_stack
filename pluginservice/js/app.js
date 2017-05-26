// endpoint is the API endpoint on the pluginservice which proxies for RES
// proper
window.App = function (endpoint) {
  this.searchForm = SearchForm('#search-form');
  this.searchResultsPanel = SearchResultsPanel('#search-results-panel');
  this.topicPanel = TopicPanel('#topic-panel');
  this.client = RESClient(endpoint);
};

var SearchForm = function (selector) {
  var element = $(selector);
};

var SearchResultsPanel = function (selector) {
  var element = $(selector);
};

var TopicPanel = function (selector) {
  var element = $(selector);
};

var RESClient = function (endpoint) {
  var offset = 0;

  this.reset = function () {
    offset = 0;
  };

  this.search = function (query) {
    var url = endpoint + '?q=' + encodeURIComponent(query) +
      '&offset=' + offset;

    // TODO trigger fetching URL event

    $.ajax({
      url: url,

      dataType: 'json',

      success: function (content) {
        // TODO trigger results event
      },

      error: function (err) {
        // TODO trigger error event
      }
    });
  };

  // topicUri is the full URI for the topic
  this.topic = function (topicUri) {
  };
};
