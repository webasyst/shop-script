"use strict";
class SkeletonLoader {
  constructor({ skeleton, content, delay, show = false, deleteSkeleton = true }) {
    if (content) {
      this.content = content
    } else {
      this.content = '[data-skeleton="' + skeleton + '"]';
    }

    this.$content = $(this.content);
    this.$skeleton = $(skeleton);
    this.delay = delay;

    if (show) {
      this.show();
    }

    this.loadedAjaxList = {};
    this.loadedAjaxList['page'] = true;
    this.isLoaded = false;
    this.deleteSkeleton = deleteSkeleton;
  }

  addAjaxPending(key) {
    this.loadedAjaxList[key] = true;
  }

  removeAjaxPending(key) {
    delete this.loadedAjaxList[key];
    this.delay = 0;
  }

  checkFinishAjaxPending() {
    if (!this.isLoaded && !Object.values(this.loadedAjaxList).length) {
      this.isLoaded = true;
    }

    return this.isLoaded;
  }

  show() {
    this.isLoaded = false;
    this.$skeleton.show();
    this.$content.hide();
  }

  repeatShow() {
    this.addAjaxPending('page');
    this.show();
  }

  hide(withCheck = true) {
    if (withCheck && this.checkFinishAjaxPending()) {
      return;
    }

    const handler = () => {
      this.removeAjaxPending('page');

      if (!this.checkFinishAjaxPending()) {
        this.startTimerAjaxPending();
        return;
      }

      this.finishTimerFinishAjaxPending();
      this.$skeleton.hide();
      if (this.deleteSkeleton) {
        this.$skeleton.remove();
      }
      this.$content.show();
      if (typeof this.onLoadedContentCallback === "function") {
        this.onLoadedContentCallback();
      }
    }

    if (this.delay && this.delay > 0) {
      setTimeout(handler, this.delay);
    } else {
      handler();
    }
  }

  onLoadedContent(callback) {
    if (this.isLoaded && typeof callback === "function") {
      callback();
    }

    this.onLoadedContentCallback = callback;
  }

  startTimerAjaxPending() {
    if (this.timerAjax) {
      return;
    }

    this.timerAjax = setInterval(() => {
      if (this.checkFinishAjaxPending()) {
        this.hide(false);
      }
    }, 250);
  }
  finishTimerFinishAjaxPending() {
    if (this.timerAjax) {
      clearInterval(this.timerAjax);
    }
  }
}

class SkeletonLoaderGroup {
  constructor(data = []) {
    data.forEach(el => {
      if (el.name) {
        this[el.name] = new SkeletonLoader(el)
      }
    });
  }

  allHide(delay = 0) {
    for (const loader of Object.values(this)) {
      loader.delay = delay;
      loader.hide();
    }
  }
}

(function ($) {
  $.fn.skeletonLoader = function (options) {
    if (options) {
      delete options['skeleton'];
    }

    var id = this.prop('id');
    if (id) {
      id = '#' + id;
    }

    var config = $.extend({
      skeleton: id || this
    }, options);

    $.skeletonLoader = new SkeletonLoader(config);
  };
})(jQuery)
