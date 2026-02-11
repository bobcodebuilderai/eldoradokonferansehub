/**
 * Wordcloud Generator
 * Custom implementation without external dependencies
 */

function generateWordcloud(containerId, words) {
    const container = document.getElementById(containerId);
    if (!container || !words.length) return;
    
    container.innerHTML = '';
    
    const containerRect = container.getBoundingClientRect();
    const containerWidth = containerRect.width;
    const containerHeight = containerRect.height;
    
    const maxCount = Math.max(...words.map(w => w.count));
    const minCount = Math.min(...words.map(w => w.count));
    
    const placedWords = [];
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#6366f1', '#14b8a6'];
    
    words.forEach((word, index) => {
        const size = Math.max(12, 48 * (word.count / maxCount));
        const span = document.createElement('span');
        span.textContent = word.text;
        span.style.fontSize = size + 'px';
        span.style.color = colors[index % colors.length];
        span.style.position = 'absolute';
        span.style.whiteSpace = 'nowrap';
        span.style.fontWeight = 'bold';
        span.style.transition = 'all 0.5s ease';
        
        container.appendChild(span);
        
        // Simple spiral placement
        let angle = 0;
        let radius = 0;
        const step = 0.5;
        const spiralGap = 10;
        
        const spanRect = span.getBoundingClientRect();
        const spanWidth = spanRect.width;
        const spanHeight = spanRect.height;
        
        let placed = false;
        let attempts = 0;
        const maxAttempts = 500;
        
        while (!placed && attempts < maxAttempts) {
            const x = containerWidth / 2 + radius * Math.cos(angle) - spanWidth / 2;
            const y = containerHeight / 2 + radius * Math.sin(angle) - spanHeight / 2;
            
            if (x >= 0 && x + spanWidth <= containerWidth && y >= 0 && y + spanHeight <= containerHeight) {
                // Check collision
                let collides = false;
                for (const placedWord of placedWords) {
                    if (!(x + spanWidth < placedWord.x || 
                          x > placedWord.x + placedWord.width || 
                          y + spanHeight < placedWord.y || 
                          y > placedWord.y + placedWord.height)) {
                        collides = true;
                        break;
                    }
                }
                
                if (!collides) {
                    span.style.left = x + 'px';
                    span.style.top = y + 'px';
                    placedWords.push({ x, y, width: spanWidth, height: spanHeight });
                    placed = true;
                }
            }
            
            angle += step;
            radius += spiralGap * step / (2 * Math.PI);
            attempts++;
        }
        
        if (!placed) {
            span.remove();
        }
    });
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { generateWordcloud };
}
