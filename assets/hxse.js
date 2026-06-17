/**
 * HXSE — Code-First Search
 * htmx + REST API による検索UIの補助スクリプト。
 *
 * このファイルが担うのは：
 *   1. URLパラメータ管理（hx-push-urlの代替）
 *   2. Rangeフィルターのmin/max連動・値表示
 *   3. リセットボタン後の再検索
 *   4. ページャーのページ番号をformに反映
 *   5. モバイル折りたたみトグル
 */
( function () {
	'use strict';

	// -----------------------------------------------------------------------
	// URLパラメータ管理
	// hx-push-urlはREST APIのURLをそのまま反映してしまうため使用しない
	// htmx:afterRequestイベントでフォームの値からURLを組み立てて更新する
	// -----------------------------------------------------------------------
	document.addEventListener( 'htmx:afterRequest', function ( e ) {
		const form = e.target.closest( '.hxse-filters' );
		if ( ! form ) return;
		if ( form.dataset.urlEnable !== '1' ) return;

		const urlMode = form.dataset.urlMode || 'always';

		// submit_onlyモード: submitトリガーのときだけURL更新
		if ( 'submit_only' === urlMode ) {
			const triggerElt = e.detail && e.detail.requestConfig && e.detail.requestConfig.triggeringEvent;
			if ( ! triggerElt || triggerElt.type !== 'submit' ) return;
		}

		// フォームの値からURLパラメータを組み立て
		const formData = new FormData( form );
		const params   = new URLSearchParams();

		formData.forEach( function ( val, key ) {
			// 内部パラメータは除外
			if ( [ 'id', 'page', 'hxse_append' ].indexOf( key ) !== -1 ) return;
			if ( val !== '' ) params.append( key, val );
		} );

		const newUrl = window.location.pathname + ( params.toString() ? '?' + params.toString() : '' );
		window.history.pushState( {}, '', newUrl );
	} );

	// -----------------------------------------------------------------------
	// Rangeフィルター: min/maxの連動・値表示
	// -----------------------------------------------------------------------
	function initRangeFilters() {
		document.querySelectorAll( '.hxse-range-wrap' ).forEach( function ( wrap ) {
			const minInput = wrap.querySelector( '.hxse-range-min' );
			const maxInput = wrap.querySelector( '.hxse-range-max' );
			const minLabel = wrap.querySelector( '.hxse-range-min-val' );
			const maxLabel = wrap.querySelector( '.hxse-range-max-val' );

			if ( ! minInput || ! maxInput ) return;

			minInput.addEventListener( 'input', function () {
				let minVal = parseFloat( minInput.value );
				const maxVal = parseFloat( maxInput.value );
				if ( minVal > maxVal ) { minInput.value = maxVal; minVal = maxVal; }
				if ( minLabel ) minLabel.textContent = minVal;
			} );

			maxInput.addEventListener( 'input', function () {
				const minVal = parseFloat( minInput.value );
				let maxVal = parseFloat( maxInput.value );
				if ( maxVal < minVal ) { maxInput.value = minVal; maxVal = minVal; }
				if ( maxLabel ) maxLabel.textContent = maxVal;
			} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', initRangeFilters );
	document.addEventListener( 'htmx:afterSwap',   initRangeFilters );

	// -----------------------------------------------------------------------
	// リセットボタン: フォームリセット後に再検索・URLクリア
	// -----------------------------------------------------------------------
	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.hxse-reset' );
		if ( ! btn ) return;
		const form = btn.closest( '.hxse-filters' );
		if ( ! form ) return;

		// URLパラメータをクリア
		if ( form.dataset.urlEnable === '1' ) {
			window.history.pushState( {}, '', window.location.pathname );
		}

		// フォームリセット後に再検索
		setTimeout( function () { htmx.trigger( form, 'submit' ); }, 0 );
	} );

	// -----------------------------------------------------------------------
	// ページャー: ページ番号をformのhidden inputに反映・結果先頭にスクロール
	// -----------------------------------------------------------------------
	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.hxse-pager-btn, .hxse-loadmore-btn' );
		if ( ! btn ) return;
		const hxseId    = btn.dataset.hxseId || btn.closest( '[data-hxse-id]' )?.dataset?.hxseId;
		const page      = btn.dataset.page;
		const form      = hxseId ? document.querySelector( '#hxse-form-' + hxseId ) : null;
		if ( form && page ) {
			const pageInput = form.querySelector( '.hxse-page-input' );
			if ( pageInput ) pageInput.value = page;
		}
	} );

	// 結果更新後に結果エリア先頭へスクロール（ページネーションクリック時のみ）
	let hxsePagerClicked = false;

	document.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '.hxse-pager-btn' ) ) {
			hxsePagerClicked = true;
		}
	} );

	document.addEventListener( 'htmx:afterSwap', function ( e ) {
		const resultsWrap = e.target.closest( '.hxse-results-wrap' );
		if ( ! resultsWrap ) return;

		if ( ! hxsePagerClicked ) return;
		hxsePagerClicked = false;

		// 結果エリアの上端にスムーズスクロール
		const top = resultsWrap.getBoundingClientRect().top + window.pageYOffset - 16;
		window.scrollTo( { top: top, behavior: 'smooth' } );
	} );

	// -----------------------------------------------------------------------
	// タブ・表示切り替えボタン: クリック後にis-active更新 + hidden inputを更新
	// -----------------------------------------------------------------------
	document.addEventListener( 'click', function ( e ) {
		// タブ
		const tab = e.target.closest( '.hxse-tab-btn' );
		if ( tab ) {
			const tabGroup = tab.closest( '.hxse-tabs' );
			if ( tabGroup ) {
				tabGroup.querySelectorAll( '.hxse-tab-btn' ).forEach( function ( btn ) {
					btn.classList.remove( 'is-active' );
					btn.setAttribute( 'aria-selected', 'false' );
				} );
				tab.classList.add( 'is-active' );
				tab.setAttribute( 'aria-selected', 'true' );
			}

			// フォームのtab hidden inputを更新
			const hxseId   = tab.closest( '.hxse-wrap' ) && tab.closest( '.hxse-wrap' ).dataset.hxseId;
			const tabInput = hxseId ? document.querySelector( '#hxse-form-' + hxseId + ' .hxse-tab-input' ) : null;
			if ( tabInput ) {
				const tabVals = JSON.parse( tab.getAttribute( 'hx-vals' ) || '{}' );
				tabInput.value = tabVals.tab !== undefined ? tabVals.tab : 0;
			}
		}

		// 表示切り替えアイコン
		const displayBtn = e.target.closest( '.hxse-display-btn' );
		if ( displayBtn ) {
			const switcher = displayBtn.closest( '.hxse-display-switcher' );
			if ( switcher ) {
				switcher.querySelectorAll( '.hxse-display-btn' ).forEach( function ( btn ) {
					btn.classList.remove( 'is-active' );
					btn.setAttribute( 'aria-pressed', 'false' );
				} );
				displayBtn.classList.add( 'is-active' );
				displayBtn.setAttribute( 'aria-pressed', 'true' );
			}

			// フォームのdisplay hidden inputを更新
			const wrap        = displayBtn.closest( '.hxse-wrap' );
			const wrapId      = wrap && wrap.dataset.hxseId;
			const displayInput = wrapId ? document.querySelector( '#hxse-form-' + wrapId + ' .hxse-display-input' ) : null;
			if ( displayInput ) {
				const displayVals = JSON.parse( displayBtn.getAttribute( 'hx-vals' ) || '{}' );
				if ( displayVals.display ) {
					displayInput.value = displayVals.display;
				}
			}
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.hxse-filter-toggle' );
		if ( ! btn ) return;

		const bodyId = btn.getAttribute( 'aria-controls' );
		const body   = bodyId ? document.getElementById( bodyId ) : null;
		if ( ! body ) return;

		const isOpen = btn.getAttribute( 'aria-expanded' ) === 'true';
		btn.setAttribute( 'aria-expanded', isOpen ? 'false' : 'true' );
		body.classList.toggle( 'is-open', ! isOpen );
	} );

} )();
