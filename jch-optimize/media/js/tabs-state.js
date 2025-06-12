/**
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * JavaScript behavior to allow selected tab to be remained after save or page reload
 * keeping state in localstorage
 */

jQuery(function ($) {
  const loadTabs = function () {
    function saveActiveTab (href) {
      if (activeTabsHrefs === null) {
        activeTabsHrefs = [];
      }
      
      // Save clicked tab href to the array
      activeTabsHrefs.push(href);
      
      // Store the selected tabs hrefs in localstorage
      localStorage.setItem('active-tabs', JSON.stringify(activeTabsHrefs));
    }
    
    function activateTab (href) {
      $('a[data-bs-toggle="tab"][href="' + href + '"]').tab('show');
    }
    
    function hasTab (href) {
      return $('a[data-bs-toggle="tab"][href="' + href + '"]').length;
    }
   
    let activeTabsHrefs = [];
    
    const fragment = window.location.hash;
    if (fragment !== '') {
      saveActiveTab(fragment + '-tab');
    }
    // Array with active tabs hrefs
    activeTabsHrefs = JSON.parse(localStorage.getItem('active-tabs'));
    
    // jQuery object with all tabs links
    const $tabs = $('a[data-bs-toggle="tab"]');
    
    $tabs.on('click', function (e) {
      window.history.pushState("", document.title, window.location.href.split('#')[0]);
      saveActiveTab($(e.currentTarget).attr('href'))
    });
    
    if (activeTabsHrefs !== null) {
      // Clean default tabs
      $tabs.parent('.active').removeClass('active');
      
      // When moving from tab area to a different view
      $.each(activeTabsHrefs, function (index, tabHref) {
        if (!hasTab(tabHref)) {
          localStorage.removeItem('active-tabs');
          
          return true;
        }
        
        // Add active attribute for selected tab indicated by url
        activateTab(tabHref);
        
        // Check whether internal tab is selected (in format <tabname>-<id>)
        const separatorIndex = tabHref.indexOf('-');
        
        if (separatorIndex !== -1) {
          const singular = tabHref.substring(0, separatorIndex)
          const plural = singular + 's'
          activateTab(plural)
        }
      })
    } else {
      $tabs.parents('ul').each(function (index, ul) {
        // If no tabs is saved, activate first tab from each tab set and save it
        const href = $(ul).find('a').first().tab('show').attr('href')
        saveActiveTab(href)
      })
    }
  };
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadTabs);
  } else {
    loadTabs();
  }
})
