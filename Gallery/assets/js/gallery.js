document.addEventListener('DOMContentLoaded', function () {
  // 性能注：初始加载 12 张图片，后续通过分页加载
  var configEl = document.getElementById('pageConfig');
  if (!configEl) return;

  var isAdmin = configEl.getAttribute('data-is-admin') === '1';
  var perPage = parseInt(configEl.getAttribute('data-per-page') || '12', 10);

  var totalImagesCount = 0;
  var currentPage = 1;
  var allImages = [];
  var allImagesLoaded = false;
  var currentPageImages = [];
  var imageCache = {}; // 缓存已加载的页面

  var listContainer = document.getElementById('imageList');
  var paginationContainer = document.getElementById('paginationContainer');

  var imageViewer = document.getElementById('imageViewer');
  var imageViewerImg = document.getElementById('imageViewerImg');
  var imageViewerTitle = document.getElementById('imageViewerTitle');
  var imageViewerDescription = document.getElementById('imageViewerDescription');
  var imageCounter = document.getElementById('imageCounter');
  var closeImageViewer = document.getElementById('closeImageViewer');
  var prevImageBtn = document.getElementById('prevImageBtn');
  var nextImageBtn = document.getElementById('nextImageBtn');
  var imageViewerSpinner = document.getElementById('imageViewerSpinner');

  var currentImageIndex = 0;
  var wheelDebounceTimer = null;
  var editModalInstance = null;
  var PRELOAD_RANGE = 6;
  var viewerActiveImageId = null;
  var allImagesLoading = false;
  var viewerPendingDirection = 0;

  var imagePreloadMap = {};
  var preloadQueue = [];
  var preloadActive = 0;
  var PRELOAD_CONCURRENCY = 3;

  function resolveImageUrl(image) {
    if (!image) return '';
    if (image.image_url) return image.image_url;
    if (image.is_remote || String(image.file_path).startsWith('http')) {
      return image.file_path;
    }
    if (String(image.file_path).startsWith('uploads/')) {
      return image.file_path;
    }
    return 'uploads/' + image.file_path;
  }

  function preloadImage(url) {
    if (!url) return Promise.reject(new Error('empty url'));
    if (imagePreloadMap[url]) return imagePreloadMap[url];

    imagePreloadMap[url] = new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = resolve;
      img.onerror = function () {
        delete imagePreloadMap[url];
        reject(new Error('load failed'));
      };
      img.src = url;
    });

    return imagePreloadMap[url];
  }

  function enqueuePreload(urls) {
    if (!Array.isArray(urls)) return;
    urls.forEach(function (url) {
      if (!url) return;
      if (imagePreloadMap[url]) return;
      if (preloadQueue.indexOf(url) !== -1) return;
      preloadQueue.push(url);
    });
    pumpPreload();
  }

  function pumpPreload() {
    while (preloadActive < PRELOAD_CONCURRENCY && preloadQueue.length > 0) {
      var nextUrl = preloadQueue.shift();
      preloadActive += 1;
      preloadImage(nextUrl).then(function () {
        preloadActive -= 1;
        pumpPreload();
      }, function () {
        preloadActive -= 1;
        pumpPreload();
      });
    }
  }

  function getErrorPlaceholderUrl() {
    return 'data:image/svg+xml;utf8,' +
      '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">' +
      '<rect width="200" height="200" fill="%23111111"/>' +
      '<path d="M50 50 L150 150 M150 50 L50 150" stroke="%23aaaaaa" stroke-width="10" stroke-linecap="round"/>' +
      '</svg>';
  }

  function updateImageViewer(direction) {
    var image = allImages[currentImageIndex];
    var imageUrl = resolveImageUrl(image);

    viewerActiveImageId = image ? image.id : null;

    // 添加滑出动画
    if (direction === 1) {
      imageViewerImg.classList.add('slide-out-left');
    } else if (direction === -1) {
      imageViewerImg.classList.add('slide-out-right');
    }

    setTimeout(function () {
      imageViewerImg.classList.remove('slide-out-left', 'slide-out-right', 'slide-in-left', 'slide-in-right');
      imageViewerImg.classList.add('is-loading');
      imageViewerImg.classList.remove('is-error', 'loaded');
      imageViewerSpinner.classList.add('is-visible');

      imageViewerTitle.textContent = image ? image.title : '';
      imageViewerDescription.textContent = image ? image.description : '';

      var total = allImagesLoaded ? allImages.length : totalImagesCount;
      imageCounter.textContent = (currentImageIndex + 1) + '/' + total;

      if (!imageUrl) {
        imageViewerImg.classList.remove('is-loading');
        imageViewerSpinner.classList.remove('is-visible');
        imageViewerImg.classList.add('is-error', 'loaded');
        imageViewerImg.style.backgroundImage = "url('" + getErrorPlaceholderUrl() + "')";
        return;
      }

      preloadImage(imageUrl)
        .then(function () {
          imageViewerImg.style.backgroundImage = "url('" + imageUrl + "')";
          imageViewerImg.classList.remove('is-loading');
          imageViewerSpinner.classList.remove('is-visible');

          // 添加滑入动画
          if (direction === 1) {
            imageViewerImg.classList.add('slide-in-right');
          } else if (direction === -1) {
            imageViewerImg.classList.add('slide-in-left');
          }

          imageViewerImg.classList.add('loaded');
          preloadNeighbors();
        })
        .catch(function () {
          imageViewerImg.classList.remove('is-loading');
          imageViewerSpinner.classList.remove('is-visible');
          imageViewerImg.classList.add('is-error', 'loaded');
          imageViewerImg.style.backgroundImage = "url('" + getErrorPlaceholderUrl() + "')";
        });
    }, direction ? 100 : 0);
  }

  function preloadNeighbors() {
    var urls = [];
    for (var offset = 1; offset <= PRELOAD_RANGE; offset += 1) {
      var prevIndex = currentImageIndex - offset;
      var nextIndex = currentImageIndex + offset;

      if (prevIndex >= 0 && prevIndex < allImages.length) {
        urls.push(resolveImageUrl(allImages[prevIndex]));
      }

      if (nextIndex >= 0 && nextIndex < allImages.length) {
        urls.push(resolveImageUrl(allImages[nextIndex]));
      }
    }
    enqueuePreload(urls);
  }

  function appendPageItems(pageData) {
    if (!pageData || !Array.isArray(pageData.items)) return false;
    var seen = {};
    allImages.forEach(function (img) { seen[img.id] = true; });

    pageData.items.forEach(function (img) {
      if (!seen[img.id]) {
        allImages.push(img);
        seen[img.id] = true;
      }
    });

    if (totalImagesCount && allImages.length >= totalImagesCount) {
      allImagesLoaded = true;
      allImagesLoading = false;
    }

    applyPendingAdvance();
    return true;
  }

  function applyPendingAdvance() {
    if (viewerPendingDirection === 1 && currentImageIndex < allImages.length - 1) {
      viewerPendingDirection = 0;
      currentImageIndex += 1;
      updateImageViewer(1);
    }
  }

  function fetchNextPageForViewer() {
    var totalPages = Math.ceil(totalImagesCount / perPage) || 1;
    var nextPage = Math.floor(allImages.length / perPage) + 1;
    if (nextPage > totalPages) return;

    if (imageCache[nextPage]) {
      appendPageItems(imageCache[nextPage]);
      return;
    }

    if (allImagesLoading) return;
    allImagesLoading = true;
    fetch('get_images.php?page=' + nextPage + '&per_page=' + perPage)
      .then(function (r) { return r.json(); })
      .then(function (pageData) {
        if (pageData && Array.isArray(pageData.items)) {
          imageCache[nextPage] = pageData;
          appendPageItems(pageData);
          allImagesLoading = false;
        }
      })
      .catch(function () {
        allImagesLoading = false;
      });
  }

  function openImageViewer(localIndex) {
    var localImage = currentPageImages[localIndex];
    if (!localImage) return;

    viewerActiveImageId = localImage.id;

    currentImageIndex = allImages.findIndex(function (img) {
      return img.id === localImage.id;
    });
    if (currentImageIndex < 0) {
      currentImageIndex = localIndex;
    }

    updateImageViewer(0);
    imageViewer.style.display = 'flex';
    // 使用 requestAnimationFrame 确保动画正确触发
    setTimeout(function () {
      imageViewer.classList.add('show');
    }, 0);
    document.body.style.overflow = 'hidden';

    if (!allImagesLoaded) {
      loadAllImages(localImage.id);
    }
  }

  function closeImageViewerHandler() {
    imageViewer.classList.remove('show');
    setTimeout(function () {
      imageViewer.style.display = 'none';
    }, 300);  // 等待动画完成
    document.body.style.overflow = '';
  }

  function showPrevImage() {
    if (currentImageIndex > 0) {
      currentImageIndex -= 1;
      updateImageViewer(-1);
    } else if (!allImagesLoaded) {
      viewerPendingDirection = 1;
      fetchNextPageForViewer();
    }
  }

  function showNextImage() {
    if (currentImageIndex < allImages.length - 1) {
      currentImageIndex += 1;
      updateImageViewer(1);
    } else if (!allImagesLoaded) {
      viewerPendingDirection = 1;
      fetchNextPageForViewer();
    }
  }

  function handleKeyDown(e) {
    if (imageViewer.style.display !== 'flex') return;
    if (e.key === 'ArrowLeft') {
      showPrevImage();
    } else if (e.key === 'ArrowRight') {
      showNextImage();
    } else if (e.key === 'Escape') {
      closeImageViewerHandler();
    }
  }

  function loadAllImages(currentId) {
    // 🎯 核心：异步瀑布流加载所有分页数据
    console.log('🌊 启动瀑布流加载所有图片...');

    if (allImagesLoading) return;
    allImagesLoading = true;

    var totalPages = Math.ceil(totalImagesCount / perPage);
    var loadedPages = 1;  // 首页已加载
    var allLoadedImages = currentPageImages.slice();  // 复制当前页数据

    // 显示加载进度
    if (totalPages > 1) {
      var progressText = document.createElement('div');
      progressText.style.cssText = 'position:absolute;bottom:30px;left:50%;transform:translateX(-50%);color:#fff;font-size:14px;background:rgba(0,0,0,0.6);padding:8px 16px;border-radius:4px;z-index:5;';
      progressText.id = 'loadProgressText';
      progressText.textContent = '加载中 1/' + totalPages;
      imageViewerSpinner.parentElement.appendChild(progressText);
    }

    // 瀑布流加载：依次加载其他分页
    var loadPageSequence = function (fromPage) {
      if (fromPage > totalPages) {
        // ✅ 全部加载完成
        allImages = allLoadedImages;
        allImagesLoaded = true;
        allImagesLoading = false;

        if (viewerActiveImageId === currentId) {
          var idx = allImages.findIndex(function (img) { return img.id === currentId; });
          if (idx >= 0) {
            currentImageIndex = idx;
          }
        }

        console.log('✅ 瀑布流加载完成！共 ' + allImages.length + ' 张图片');
        imageCounter.textContent = (currentImageIndex + 1) + '/' + allImages.length;

        // 移除进度指示
        var progressEl = document.getElementById('loadProgressText');
        if (progressEl) progressEl.remove();
        return;
      }

      // 异步加载下一分页（错开请求）
      setTimeout(function () {
        fetch('get_images.php?page=' + fromPage + '&per_page=' + perPage)
          .then(function (r) { return r.json(); })
          .then(function (pageData) {
            if (pageData && Array.isArray(pageData.items)) {
              allLoadedImages = allLoadedImages.concat(pageData.items);
              loadedPages++;
              allImages = allLoadedImages;
              appendPageItems(pageData);

              // 更新进度
              var progressEl = document.getElementById('loadProgressText');
              if (progressEl) {
                progressEl.textContent = '加载中 ' + loadedPages + '/' + totalPages;
              }

              console.log('📄 已加载第 ' + fromPage + ' 页 (' + loadedPages + '/' + totalPages + ')');
            }
            // 继续加载下一页
            loadPageSequence(fromPage + 1);
          })
          .catch(function (err) {
            console.warn('⚠️ 加载第 ' + fromPage + ' 页失败: ' + err);
            // 继续加载下一页
            loadPageSequence(fromPage + 1);
          });
      }, 300 * fromPage);  // 每页相隔 300ms，避免突发流量
    };

    // 从第 2 页开始加载（第 1 页已在初始化时加载）
    if (totalPages > 1) {
      loadPageSequence(2);
    } else {
      // 只有一页，直接标记为已加载
      allImages = allLoadedImages;
      allImagesLoaded = true;
      allImagesLoading = false;
      imageCounter.textContent = (currentImageIndex + 1) + '/' + allImages.length;
    }
  }

  function applyListBackground(el) {
    var url = el.getAttribute('data-bg');
    if (!url) return;
    preloadImage(url)
      .then(function () {
        el.style.backgroundImage = "url('" + url + "')";
        el.classList.remove('is-loading');
      })
      .catch(function () {
        el.classList.remove('is-loading');
      });
  }

  function lazyLoadList() {
    var items = document.querySelectorAll('.image-container[data-bg]');
    if (!('IntersectionObserver' in window)) {
      items.forEach(function (item) { applyListBackground(item); });
      return;
    }

    var observer = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          applyListBackground(entry.target);
          obs.unobserve(entry.target);
        }
      });
    }, { rootMargin: '200px 0px' });  // 提前 200px 开始加载图片

    items.forEach(function (item) { observer.observe(item); });
  }

  function renderList(items) {
    var fragment = document.createDocumentFragment();
    items.forEach(function (image, index) {
      var card = document.createElement('div');
      card.className = 'image-card card h-100';
      card.setAttribute('data-image-id', String(image.id));
      card.setAttribute('data-image-index', String(index));
      card.addEventListener('click', function () {
        openImageViewer(index);
      });

      var imgBox = document.createElement('div');
      imgBox.className = 'image-container list-img-container is-loading';
      imgBox.setAttribute('data-bg', resolveImageUrl(image));
      card.appendChild(imgBox);

      var body = document.createElement('div');
      body.className = 'card-body';

      var title = document.createElement('h5');
      title.className = 'card-title list-img-title';
      title.textContent = image.title || '';

      var desc = document.createElement('p');
      desc.className = 'card-text list-img-desc';
      desc.textContent = image.description || '';

      body.appendChild(title);
      body.appendChild(desc);

      if (isAdmin) {
        var btns = document.createElement('div');
        btns.className = 'admin-buttons d-flex gap-2';

        var editBtn = document.createElement('button');
        editBtn.className = 'btn btn-sm btn-warning';
        editBtn.textContent = '编辑';
        editBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          openEditModal(image.id);
        });

        var deleteBtn = document.createElement('button');
        deleteBtn.className = 'btn btn-sm btn-danger';
        deleteBtn.textContent = '删除';
        deleteBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          confirmDelete(image.id);
        });

        btns.appendChild(editBtn);
        btns.appendChild(deleteBtn);
        body.appendChild(btns);
      }

      card.appendChild(body);
      fragment.appendChild(card);
    });
    listContainer.innerHTML = '';
    listContainer.appendChild(fragment);
    lazyLoadList();
  }

  function renderPagination(total, page, pageSize) {
    var totalPages = Math.max(1, Math.ceil(total / pageSize));
    paginationContainer.innerHTML = '';
    if (totalPages <= 1) return;

    var nav = document.createElement('nav');
    var ul = document.createElement('ul');
    ul.className = 'pagination';

    function addItem(label, targetPage, disabled, active) {
      var li = document.createElement('li');
      li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');

      var a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = label;

      if (!disabled) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          fetchPage(targetPage);
        });
      }

      li.appendChild(a);
      ul.appendChild(li);
    }

    addItem('上一页', page - 1, page <= 1, false);

    var startPage = Math.max(1, page - 2);
    var endPage = Math.min(totalPages, page + 2);

    if (startPage > 1) {
      addItem('1', 1, false, page === 1);
      if (startPage > 2) {
        var ellipsisLi = document.createElement('li');
        ellipsisLi.className = 'page-item disabled';
        var span = document.createElement('span');
        span.className = 'page-link';
        span.textContent = '...';
        ellipsisLi.appendChild(span);
        ul.appendChild(ellipsisLi);
      }
    }

    for (var p = startPage; p <= endPage; p += 1) {
      addItem(String(p), p, false, p === page);
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        var ellipsisLiEnd = document.createElement('li');
        ellipsisLiEnd.className = 'page-item disabled';
        var spanEnd = document.createElement('span');
        spanEnd.className = 'page-link';
        spanEnd.textContent = '...';
        ellipsisLiEnd.appendChild(spanEnd);
        ul.appendChild(ellipsisLiEnd);
      }
      addItem(String(totalPages), totalPages, false, page === totalPages);
    }

    addItem('下一页', page + 1, page >= totalPages, false);

    nav.appendChild(ul);
    paginationContainer.appendChild(nav);
  }

  function fetchPage(page) {
    // 检查缓存
    if (imageCache[page]) {
      var cachedData = imageCache[page];
      currentPage = cachedData.page;
      totalImagesCount = cachedData.total;
      currentPageImages = cachedData.items;
      allImages = cachedData.items;
      renderList(currentPageImages);
      renderPagination(totalImagesCount, currentPage, perPage);
      imageCounter.textContent = '1/' + totalImagesCount;
      return;
    }

    var fetchStart = performance.now();
    fetch('get_images.php?page=' + page + '&per_page=' + perPage)
      .then(function (response) {
        var fetchTime = performance.now() - fetchStart;

        // 从 Server-Timing 头解析时间
        var serverTiming = response.headers.get('Server-Timing') || '';
        console.log('📊 API网络遍历: ' + fetchTime.toFixed(1) + 'ms');
        if (serverTiming) {
          console.log('📋 Server-Timing: ' + serverTiming);
        }

        return response.json();
      })
      .then(function (data) {
        if (!data || !Array.isArray(data.items)) return;
        // 缓存这一页的数据
        imageCache[data.page] = data;
        currentPage = data.page;
        totalImagesCount = data.total;
        currentPageImages = data.items;
        allImages = data.items;
        allImagesLoaded = false;

        var renderStart = performance.now();
        renderList(currentPageImages);
        console.log('🎨 列表渲染时间: ' + (performance.now() - renderStart).toFixed(1) + 'ms');

        renderPagination(totalImagesCount, currentPage, perPage);
        imageCounter.textContent = '1/' + totalImagesCount;

        // 异步预加载相邻页面（非关键）
        if (data.page < Math.ceil(totalImagesCount / perPage)) {
          setTimeout(function () {
            fetch('get_images.php?page=' + (data.page + 1) + '&per_page=' + perPage)
              .then(function (r) { return r.json(); })
              .then(function (d) { imageCache[d.page] = d; })
              .catch(function () { });
          }, 500);
        }
      })
      .catch(function (error) {
        console.error('加载图片列表失败:', error);
        if (window.showToast) window.showToast('加载图片列表失败，请稍后再试', 'warning');
      });
  }

  function updateListItem(data) {
    var card = listContainer.querySelector('[data-image-id="' + data.id + '"]');
    if (!card) return;
    var titleEl = card.querySelector('.list-img-title');
    var descEl = card.querySelector('.list-img-desc');
    var imgEl = card.querySelector('.image-container');

    if (titleEl) titleEl.textContent = data.title || '';
    if (descEl) descEl.textContent = data.description || '';
    if (imgEl) {
      imgEl.setAttribute('data-bg', resolveImageUrl(data));
      imgEl.classList.add('is-loading');
      applyListBackground(imgEl);
    }
  }

  function updateLocalData(data) {
    currentPageImages = currentPageImages.map(function (item) {
      if (item.id === data.id) {
        return {
          id: item.id,
          title: data.title,
          description: data.description,
          file_path: data.file_path,
          is_remote: data.is_remote
        };
      }
      return item;
    });

    allImages = allImages.map(function (item) {
      if (item.id === data.id) {
        return {
          id: item.id,
          title: data.title,
          description: data.description,
          file_path: data.file_path,
          is_remote: data.is_remote
        };
      }
      return item;
    });

    var currentImage = allImages[currentImageIndex];
    if (currentImage && currentImage.id === data.id) {
      imageViewerTitle.textContent = data.title || '';
      imageViewerDescription.textContent = data.description || '';
      imageViewerImg.style.backgroundImage = "url('" + resolveImageUrl(data) + "')";
    }
  }

  function confirmDelete(id) {
    if (confirm('确定要删除这张图片吗？')) {
      var token = configEl.getAttribute('data-csrf') || '';
      var url = 'delete_image.php?id=' + encodeURIComponent(id);
      if (token) {
        url += '&csrf_token=' + encodeURIComponent(token);
      }
      window.location = url;
    }
  }

  function openEditModal(id) {
    if (!isAdmin) return;

    var editModalEl = document.getElementById('editModal');
    var editForm = document.getElementById('editImageForm');
    var editSaveBtn = document.getElementById('editSaveBtn');
    var editTitleInput = document.getElementById('editImageTitle');
    var editDescInput = document.getElementById('editImageDescription');
    var editIdInput = document.getElementById('editImageId');
    var editFileInput = document.getElementById('editImageFile');

    if (!editModalEl || !editForm) return;

    if (!editModalInstance) {
      editModalInstance = new bootstrap.Modal(editModalEl);
    }

    fetch('get_image_json.php?id=' + id)
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data || !data.success) throw new Error('load failed');
        editIdInput.value = data.data.id;
        editTitleInput.value = data.data.title || '';
        editDescInput.value = data.data.description || '';
        editFileInput.value = '';
        editModalInstance.show();
      })
      .catch(function (error) {
        console.error('加载编辑表单失败:', error);
        if (window.showToast) window.showToast('加载编辑表单失败，请稍后再试', 'danger');
      });

    if (!editForm.dataset.bound) {
      editForm.dataset.bound = '1';
      editForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!editIdInput.value) return;

        // 如果选择了新文件，验证文件
        if (editFileInput.files && editFileInput.files.length > 0) {
          var file = editFileInput.files[0];
          var validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
          var maxSize = 6 * 1024 * 1024;

          if (validTypes.indexOf(file.type) === -1) {
            if (window.showToast) window.showToast('不支持的文件类型，请选择图片文件 (JPG, PNG, GIF, WebP)', 'danger');
            return;
          }

          if (file.size > maxSize) {
            if (window.showToast) window.showToast('文件太大 (' + formatFileSize(file.size) + '), 最大允许6MB', 'danger');
            return;
          }
        }

        var formData = new FormData(editForm);
        formData.append('ajax', '1');
        editSaveBtn.disabled = true;

        fetch('update_image.php', {
          method: 'POST',
          body: formData
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data || !data.success) throw new Error('save failed');
            updateLocalData(data.data);
            updateListItem(data.data);
            if (window.showToast) window.showToast('编辑成功', 'success');
            editModalInstance.hide();
          })
          .catch(function (error) {
            console.error('编辑失败:', error);
            if (window.showToast) window.showToast('编辑失败，请重试', 'danger');
          })
          .finally(function () {
            editSaveBtn.disabled = false;
          });
      });
    }
  }

  closeImageViewer.addEventListener('click', closeImageViewerHandler);
  prevImageBtn.addEventListener('click', showPrevImage);
  nextImageBtn.addEventListener('click', showNextImage);
  document.addEventListener('keydown', handleKeyDown);

  imageViewer.addEventListener('click', function (e) {
    if (e.target === imageViewer) {
      closeImageViewerHandler();
    }
  });

  imageViewer.addEventListener('wheel', function (e) {
    if (imageViewer.style.display !== 'flex') return;
    e.preventDefault();
    if (wheelDebounceTimer) clearTimeout(wheelDebounceTimer);
    wheelDebounceTimer = setTimeout(function () {
      if (e.deltaY > 0) {
        showNextImage();
      } else {
        showPrevImage();
      }
    }, 150);
  }, { passive: false });

  if (window.Hammer) {
    var hammer = new Hammer(imageViewerImg);
    hammer.get('swipe').set({ direction: Hammer.DIRECTION_HORIZONTAL });
    hammer.on('swipeleft', function () { showNextImage(); });
    hammer.on('swiperight', function () { showPrevImage(); });
    hammer.on('doubletap', function () { closeImageViewerHandler(); });
  }

  // 如果首页嵌入了初始数据，直接使用；否则发起 API 请求
  if (window.initialData && window.initialData.items && window.initialData.items.length > 0) {
    var data = window.initialData;
    imageCache[1] = data;
    currentPage = 1;
    totalImagesCount = data.total;
    currentPageImages = data.items;
    allImages = data.items;
    allImagesLoaded = false;
    enqueuePreload(currentPageImages.map(resolveImageUrl));
    renderList(currentPageImages);
    renderPagination(totalImagesCount, 1, perPage);
    imageCounter.textContent = '1/' + totalImagesCount;
    console.log('✨ 首页数据已注入，无需 API 请求');

    // 🚀 关键优化：预加载所有分页数据到内存（异步，不阻塞首屏）
    var totalPages = Math.ceil(totalImagesCount / perPage);
    if (totalPages > 1) {
      console.log('📦 开始预加载所有分页数据...');
      for (var page = 2; page <= totalPages; page++) {
        (function (pageNum) {
          setTimeout(function () {
            fetch('get_images.php?page=' + pageNum + '&per_page=' + perPage)
              .then(function (r) { return r.json(); })
              .then(function (pageData) {
                if (pageData && Array.isArray(pageData.items)) {
                  imageCache[pageNum] = pageData;
                  appendPageItems(pageData);
                  enqueuePreload(pageData.items.slice(0, 6).map(resolveImageUrl));
                  console.log('✅ 第 ' + pageNum + ' 页已预加载');
                }
              })
              .catch(function () { });
          }, 100 * (pageNum - 1));  // 错开请求，避免突发流量
        })(page);
      }
    }
  } else {
    fetchPage(1);
  }

  // 辅助函数：格式化文件大小
  function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' bytes';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  }
});
