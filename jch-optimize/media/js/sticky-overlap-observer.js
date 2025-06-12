/**
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/wordpress-platform
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2025 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 * If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */

class StickyOverlapObserver {
  #sentinel;
  #parent;
  #child;
  #classWhenParentBelow;
  #observer;
  
  constructor(parentSelector, childSelector, options = {
    classWhenParentBelow: ['border-top', 'border-5']
  }) {
    this.#parent = document.querySelector(parentSelector);
    this.#child = document.querySelector(childSelector);
    if (!this.#parent || !this.#child) {
      throw new Error("Parent or child element not found");
    }

    this.#classWhenParentBelow = options.classWhenParentBelow;

    this.#sentinel = document.getElementById('sticky-overlap-sentinel');
    this.#createObserver();
  }

  #createObserver() {
    this.#observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          this.#child.classList.remove(...this.#classWhenParentBelow);
        } else {
          this.#child.classList.add(...this.#classWhenParentBelow);
        }
      },
      {
        root: null,
        threshold: 0,
        rootMargin: `-${this.#child.offsetHeight}px 0px 0px 0px`
      }
    );

    this.#observer.observe(this.#sentinel);
  }

  disconnect() {
    if (this.#observer) {
      this.#observer.disconnect();
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const observer = new StickyOverlapObserver(
      '#settings-content', '#sticky-submit-button'
  );
});