// 本檔把資料中的英文分類代碼對照成畫面上的中文名稱，沒有網路或資料庫操作。
// 例如 genreLabels['RPG'] 會取得「角色扮演」。左邊是資料代碼，右邊是顯示文字。
// export const 讓其他檔案引用對照物件；改中文顯示時優先改這裡，不必更換資料庫代碼。

// 遊戲類型，例如角色扮演、動作；同一款可有多種類型。
export const genreLabels = {
    // 示例：資料存 RPG，畫面用 genreLabels[game.genre中的代碼] 取「角色扮演」；不是改資料庫內容。
    RPG: '角色扮演',
    Roguelike: '類 Rogue',
    Action: '動作',
    Adventure: '冒險',
    'Sci-Fi': '科幻',
    Horror: '恐怖',
    Mystery: '懸疑',
    Strategy: '策略',
    Simulation: '模擬',
    Casual: '休閒',
    Indie: '獨立',
    Story: '劇情',
    War: '戰爭',
    'Visual Novel': '視覺小說',
    Racing: '競速',
    Survival: '生存',
    Sports: '運動',
    Competitive: '競技',
    Puzzle: '解謎',
    Fighting: '格鬥',
    Stealth: '潛行',
    Platformer: '平台跳躍',
    Rhythm: '音樂節奏',
}

// 商城展示標籤，不是訂單狀態；new／bestseller／featured 對應首頁分組。
export const statusLabels = {
    // 商品展示狀態可同時有多個，例如新品也能是精選；與訂單 completed 狀態不是同一組代碼。
    new: '新品上市',
    bestseller: '暢銷遊戲',
    featured: '精選遊戲'
}

// 更細的玩法與特色標籤。含空白或連字號的鍵用引號包住，才能作為合法名稱。
export const tagLabels = {
    Atmospheric: '沉浸氛圍',
    // 鍵含空白必須加引號；其他標籤同樣是固定對照，不需要逐項查詢 API。
    'Base Building': '基地建造',
    'Boss Battles': '頭目戰',
    'Character Customization': '角色自訂',
    'Character Progression': '角色成長',
    'Choice Driven': '選擇導向',
    'City Building': '城市建造',
    Competitive: '競技',
    Crafting: '製作系統',
    'Deck Building': '牌組構築',
    Detective: '偵探推理',
    Diplomacy: '外交',
    'Dungeon Crawling': '地下城探索',
    Economy: '經濟系統',
    Exploration: '探索',
    Farming: '農耕',
    Fishing: '釣魚',
    'Multiple Endings': '多重結局',
    'Open World': '開放世界',
    'Procedural Generation': '程序生成',
    'Psychological Horror': '心理恐怖',
    'Puzzle Solving': '解謎',
    'Randomized Runs': '隨機冒險',
    'Real-Time Combat': '即時戰鬥',
    'Relationship System': '人際關係系統',
    'Resource Management': '資源管理',
    Romance: '戀愛',
    'Skill Tree': '技能樹',
    'Space Exploration': '太空探索',
    Stealth: '潛行',
    'Story Rich': '豐富劇情',
    Survival: '生存',
    'Tactical Combat': '戰術戰鬥',
    'Time Attack': '限時挑戰',
    Trading: '交易',
    'Vehicle Customization': '載具改裝',
    'World Building': '世界建構'
}