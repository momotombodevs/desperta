<native:top-bar :title="__('progress.daily_question.title')" show-navigation-icon />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column ref="question-of-the-day-screen" class="w-full gap-4 p-5">
        <native:daily-question-card key="daily-question-card"/>
    </native:column>
</native:scroll-view>
