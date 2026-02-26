document.addEventListener('alpine:init', () => {
    Alpine.data('presentationEngagement', ({ hasLiked, likesCount, viewsCount }) => ({
        hasLiked,
        likesCount,
        viewsCount,
        pending: false,
        async toggleLike() {
            if (this.pending) return;

            this.hasLiked = !this.hasLiked;
            this.likesCount = Math.max(0, this.likesCount + (this.hasLiked ? 1 : -1));
            this.pending = true;

            try {
                await this.$wire.toggleLike();
            } finally {
                this.pending = false;
            }
        },
    }));
});
