//
//  GameState.swift
//  ElectricSim
//
//  გლობალური მდგომარეობა: დონეები, პროგრესი, კომპონენტების ბიბლიოთეკა.
//  პროგრესი ინახება ლოკალურად (UserDefaults).
//

import Foundation
import SwiftUI

@MainActor
final class GameState: ObservableObject {
    @Published var levels: [Level] = []
    @Published var templates: [String: ComponentTemplate] = [:]
    @Published var completedLevelIDs: Set<String> = []
    @Published var loadError: String?

    private let progressKey = "completedLevelIDs.v1"

    init() {
        load()
    }

    func load() {
        do {
            templates = try GameData.loadTemplates()
            levels = try GameData.loadLevels()
        } catch {
            loadError = "მონაცემების ჩატვირთვა ვერ მოხერხდა: \(error)"
        }
        if let saved = UserDefaults.standard.array(forKey: progressKey) as? [String] {
            completedLevelIDs = Set(saved)
        }
    }

    func template(_ id: String) -> ComponentTemplate? { templates[id] }

    func isCompleted(_ level: Level) -> Bool { completedLevelIDs.contains(level.id) }

    func isUnlocked(_ level: Level) -> Bool {
        // პირველი დონე ყოველთვის ღიაა; შემდეგი — როცა წინა დასრულდა.
        guard let idx = levels.firstIndex(where: { $0.id == level.id }) else { return false }
        if idx == 0 { return true }
        return completedLevelIDs.contains(levels[idx - 1].id)
    }

    func markCompleted(_ level: Level) {
        completedLevelIDs.insert(level.id)
        UserDefaults.standard.set(Array(completedLevelIDs), forKey: progressKey)
    }

    func resetProgress() {
        completedLevelIDs.removeAll()
        UserDefaults.standard.removeObject(forKey: progressKey)
    }
}
