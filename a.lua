--// Configuration
local API_URL = "https://stagnant.my.id/api/upload-json"
local INTERVAL = 180 -- Seconds

--// Services
local Players = game:GetService("Players")
local ReplicatedStorage = game:GetService("ReplicatedStorage")
local HttpService = game:GetService("HttpService")

local player = Players.LocalPlayer
local launchWarned = false

--// Controllers & Libraries
local DataController = require(
    ReplicatedStorage:WaitForChild("client")
        :WaitForChild("legacyControllers")
        :WaitForChild("DataController")
)

local RodLibrary = require(
    ReplicatedStorage:WaitForChild("shared")
        :WaitForChild("modules")
        :WaitForChild("library")
        :WaitForChild("rods")
)

--// Wait for data
DataController.PlayerDataReplicator:WaitForLoaded()
DataController.InventoryReplicator:WaitForLoaded()
DataController.StorageReplicator:WaitForLoaded()

warn("Uploader launched, waiting for first successful sync...")

local function getIndexedData(replicator, indexName)
    local success, data = pcall(function()
        return replicator:Index({ indexName })
    end)

    if success and type(data) == "table" then
        return data
    end

    return nil
end

--// Helper: Fetch Stats from Workspace
local function getWorkspaceStats()
    local statsFolder = workspace:FindFirstChild("PlayerStats")
    local userFolder = statsFolder and statsFolder:FindFirstChild(player.Name)
    local tFolder = userFolder and userFolder:FindFirstChild("T")
    local innerUser = tFolder and tFolder:FindFirstChild(player.Name)

    return innerUser
end

--// Format Inventory/Storage Items
local function formatItems(replicator, indexName)
    local formatted = {}
    local maxItems = indexName == "Storage" and math.huge or 5000

    local data = getIndexedData(replicator, indexName)

    if data then
        local count = 0

        for itemId, itemData in pairs(data) do
            if type(itemData) == "table" and itemData.name then
                count += 1

                local sub = itemData.sub or {}

                table.insert(formatted, {
                    id = tostring(itemId),
                    name = itemData.name or "Unknown",
                    weight = tonumber(sub.Weight) or 0,
                    stack = tonumber(sub.Stack) or 1,
                    mutation = sub.Mutation or "None",
                    sparkling = sub.Sparkling or false,
                    shiny = sub.Shiny or false,
                    favourited = sub.Favourited or false
                })

                if count % 50 == 0 then
                    task.wait()
                end
            end

            if count >= maxItems then
                break
            end
        end
    end

    return formatted
end

--// Build JSON Payload
local function buildWebPayload()
    local statsRoot = getWorkspaceStats()

    --// Coins
    local currentCoins = "0"

    if statsRoot then
        local statsFolder = statsRoot:FindFirstChild("Stats")
        local coinsValue = statsFolder and statsFolder:FindFirstChild("coins")

        if coinsValue then
            currentCoins = tostring(coinsValue.Value or 0)
        end
    end

    --// Rods NEW SYSTEM, same old payload shape
    local ownedRods = {}

    local rodsData = getIndexedData(DataController.PlayerDataReplicator, "Rods")

    if rodsData then
        for rodName, _ in pairs(rodsData) do
            local rodData = RodLibrary[rodName]

            table.insert(ownedRods, {
                id = rodName,
                name = rodName,
                icon = rodData and rodData.Icon or ""
            })
        end
    end

    --// Inventory + Storage
    local formattedInventory = formatItems(
        DataController.InventoryReplicator,
        "Inventory"
    )

    local formattedStorage = formatItems(
        DataController.StorageReplicator,
        "Storage"
    )

    return HttpService:JSONEncode({
        playerName = player.Name,
        coins = currentCoins,

        inventory = formattedInventory,
        totalItems = #formattedInventory,

        storage = formattedStorage,
        totalStorageItems = #formattedStorage,

        rods = ownedRods
    })
end

--// Detect Request Function
local function getRequestFunction()
    return (syn and syn.request)
        or (http and http.request)
        or http_request
        or request
end

--// Auto Sync Loop
task.spawn(function()
    local requestFunction = getRequestFunction()

    if not requestFunction then
        warn("No valid request function found.")
        return
    end

    while true do
        local payloadSuccess, payload = pcall(buildWebPayload)

        if payloadSuccess then
            local requestSuccess, response = pcall(function()
                return requestFunction({
                    Url = API_URL,
                    Method = "POST",
                    Headers = {
                        ["Content-Type"] = "application/json"
                    },
                    Body = payload
                })
            end)

            if requestSuccess then
                if response and response.StatusCode and response.StatusCode >= 400 then
                    warn(
                        "API Error: "
                        .. tostring(response.StatusCode)
                        .. " | "
                        .. tostring(response.Body)
                    )
                elseif not launchWarned then
                    launchWarned = true
                    warn("Launch successful. First sync uploaded.")
                end
            else
                warn("Request Failed: " .. tostring(response))
            end
        else
            warn("Payload Error: " .. tostring(payload))
        end

        task.wait(INTERVAL)
    end
end)
