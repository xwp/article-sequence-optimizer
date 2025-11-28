/**
 * article-sequence-optimizer.js
 * * Strategy:
 * 1. Iframes (ignore ads) -> CSS content-visibility ( Prevents reloads/flicker )
 * 2. Text/Images -> DOM Detachment ( Reduces DOM size/INP )
 * 3. Security -> Uses direct node references ( No innerHTML/XSS risk )
 * 4. Stability -> Implements LRU Caching to prevent memory leaks
 * 5. Performance -> Batched DOM reads/writes & Scoped Queries
 */
( function() {
    function initializeArticleSequenceOptimizer() {
        const defaults = {
            containerSelector: '#articles-area',
            articleSelector: 'article',
            safelistIframes: '.ad',
            bufferDistance: '2500px 0px 2500px 0px', // Pre-load off-screen
            maxCacheSize: 50 // Keep max 50 articles in memory. Older ones get removed.
        };

        const CONFIG = Object.assign( {}, defaults, window.articleSequenceOptimizerConfig || {} );

        // Use a Map to preserve insertion order ( works as LRU cache naturally )
        const nodeCache = new Map();
        const observedSet = new WeakSet();
        const heightCache = new WeakMap(); // O(1) lookup, auto-GC when element removed

        // Batching State
        const pendingOffload = new Set();
        const pendingRestore = new Set();
        let batchScheduled = false;

        const container = document.querySelector( CONFIG.containerSelector );

        // If the container is not found, there's nothing to optimize.
        if ( !container ) {
            console.warn( `ArticleSequenceOptimizer: Container with selector "${CONFIG.containerSelector}" not found. Skipping initialization.` );
            return;
        }

        function hasEmbeds( element ) {
            // Checks for iframes, ignore if any parent has the whitelist class.
            const selector = CONFIG.safelistIframes ? `iframe:not(${CONFIG.safelistIframes} *)` : 'iframe';
            return element.querySelector( selector );
        }

        /**
         * MEMORY MANAGER
         * If cache gets too big, we permanently destroy the oldest offloaded content.
         * This prevents browser crashes on infinite sessions.
         */
        function maintainCacheSize() {
            if ( nodeCache.size > CONFIG.maxCacheSize ) {
                // Map.keys().next().value returns the first ( oldest ) key
                const oldestId = nodeCache.keys().next().value;
                nodeCache.delete( oldestId );
            }
        }

        /**
         * PERFORM OFFLOAD ( WRITE PHASE )
         * Does not measure layout. Assumes height is already set.
         */
        function performOffload( el ) {
            if ( el.classList.contains( 'inp-article-offloaded' ) || el.classList.contains( 'inp-article-visibility-optimized' ) ) {
                return;
            }

            if ( hasEmbeds( el ) ) {
                // STRATEGY A: IFRAME ( CSS Hide ) - Secure against emmbeds re-initialization
                el.classList.add( 'inp-article-visibility-optimized' );
            } else {
                // STRATEGY B: DETACHMENT ( Memory Move ) - Secure against XSS
                const fragment = document.createDocumentFragment();
                if ( ! el.id) { // Generate unique IDs if not present.
                    el.id = 'article-' + Date.now() + '-' + Math.random(); 
                }
                while ( el.firstChild ) {
                    fragment.appendChild( el.firstChild );
                }
                
                nodeCache.set( el.id, fragment );
                
                el.classList.add( 'inp-article-offloaded' );
                
                // Ensure we don't leak memory
                maintainCacheSize();
            }
        }

        /**
         * PERFORM RESTORE ( WRITE PHASE )
         */
        function performRestore( el ) {
            // Restore Strategy A
            if ( el.classList.contains( 'inp-article-visibility-optimized' ) ) {
                el.classList.remove( 'inp-article-visibility-optimized' );
                return;
            }

            // Restore Strategy B
            if ( el.classList.contains( 'inp-article-offloaded' ) ) {
                const fragment = nodeCache.get( el.id );
                
                if ( fragment ) {
                    el.appendChild( fragment );
                    nodeCache.delete( el.id ); // Remove from cache ( it's back in DOM )
                } else {
                    // EDGE CASE: We deleted it from memory ( Safety Cap ).
                    el.classList.add(  'inp-article-removed'  );
                }
                
                el.classList.remove( 'inp-article-offloaded' );
            }
        }

        /**
         * BATCH PROCESSOR
         * Runs once per frame to handle all pending offloads/restores.
         * Separates Reads ( measurements ) from Writes ( mutations ) to prevent layout thrashing.
         */
        function processBatch() {
            batchScheduled = false;

            // Snapshot and clear sets
            const toOffload = new Set( pendingOffload );
            const toRestore = new Set( pendingRestore );
            pendingOffload.clear();
            pendingRestore.clear();

            // PHASE 1: READ ( Measure heights )
            // Only measure if we don't already have it in WeakMap (avoids DOM read)
            const heights = new Map();
            toOffload.forEach( el => {
                // Skip if already offloaded
                if ( el.classList.contains( 'inp-article-offloaded' ) || el.classList.contains( 'inp-article-visibility-optimized' ) ) return;

                // Height Caching: WeakMap lookup is O(1), no DOM access
                if ( ! heightCache.has( el ) ) {
                    heights.set( el, el.offsetHeight );
                }
            } );

            // PHASE 2: WRITE ( Mutate DOM )
            
            // Apply heights first
            toOffload.forEach( el => {
                if ( heights.has( el ) ) {
                    const height = heights.get( el );
                    heightCache.set( el, height ); // Cache in WeakMap for fast lookup
                    el.style.setProperty( '--inp-article-height', `${height}px` );
                }
                performOffload( el );
            } );

            // Perform restores
            toRestore.forEach( el => {
                performRestore( el );
            } );
        }

        function scheduleBatch() {
            if ( !batchScheduled ) {
                batchScheduled = true;
                requestAnimationFrame( processBatch );
            }
        }

        // --- OBSERVERS ---
        const observer = new IntersectionObserver( ( entries ) => {
            entries.forEach( entry => {
                const el = entry.target;

                if ( !entry.isIntersecting ) {
                    // Queue for offload
                    pendingOffload.add( el );
                    pendingRestore.delete( el ); // Ensure it's not in restore queue
                } else {
                    // Queue for restore
                    pendingRestore.add( el );
                    pendingOffload.delete( el ); // Ensure it's not in offload queue
                }
            } );
            scheduleBatch();
        }, { rootMargin: CONFIG.bufferDistance } );

        function observeNewArticles() {
            // Scoped query inside container
            const articles = container.querySelectorAll( `:scope > ${CONFIG.articleSelector}` );
            
            articles.forEach( article => {
                if ( !observedSet.has( article ) ) {
                    observer.observe( article );
                    observedSet.add( article );
                }
            } );
        }

        const mutationObserver = new MutationObserver( ( mutations ) => {
            let needsUpdate = false;
            for ( const m of mutations ) {
                if ( needsUpdate ) break;
                for ( const node of m.addedNodes ) {
                    if ( node.nodeType === 1 ) { // Element node
                        // Check if the node itself is an article or contains one
                        if ( ( node.matches && node.matches( CONFIG.articleSelector ) ) || 
                            ( node.querySelector && node.querySelector( CONFIG.articleSelector ) ) ) {
                            needsUpdate = true;
                            break;
                        }
                    }
                }
            }
            if ( needsUpdate ) observeNewArticles();
        } );

        mutationObserver.observe( container, { childList: true } );
        observeNewArticles();
    }

    // Wait for the DOM to be ready before initializing the optimizer
    if ( document.readyState === 'loading' ) {
        document.addEventListener(  'DOMContentLoaded', initializeArticleSequenceOptimizer  );
    } else {
        initializeArticleSequenceOptimizer();
    }
} )();
